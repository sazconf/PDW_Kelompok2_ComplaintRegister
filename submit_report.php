<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

require_login();

$page_title = 'Submit Report - Yogyakarta City Complaint Register';
$categories = ['Infrastructure', 'Cleanliness', 'Security', 'Public Service', 'Environment'];
$allowed_extensions = ['jpg', 'jpeg', 'png'];
$allowed_mime_types = ['image/jpeg', 'image/png'];
$max_file_size = 5 * 1024 * 1024;

$errors = [];
$success_message = '';
$title = '';
$description = '';
$category = '';
$incident_date = '';
$location = '';
$latitude = '';
$longitude = '';
$extra_head = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">';
$min_incident_date = date('Y-m-d', strtotime('-100 years'));
$max_incident_date = date('Y-m-d');

function is_valid_report_date($date)
{
    $parts = explode('-', $date);

    if (count($parts) !== 3) {
        return false;
    }

    return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
}

function is_date_in_range($date, $min_date, $max_date)
{
    return $date >= $min_date && $date <= $max_date;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $incident_date = trim($_POST['incident_date'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');

    if ($title === '') {
        $errors['title'] = 'Title is required.';
    } elseif (strlen($title) < 5 || strlen($title) > 150) {
        $errors['title'] = 'Title must be between 5 and 150 characters.';
    }

    if ($description === '') {
        $errors['description'] = 'Description is required.';
    } elseif (strlen($description) < 10) {
        $errors['description'] = 'Description must be at least 10 characters.';
    }

    if ($category === '') {
        $errors['category'] = 'Category is required.';
    } elseif (!in_array($category, $categories, true)) {
        $errors['category'] = 'Please select a valid category.';
    }

    if ($incident_date === '') {
        $errors['incident_date'] = 'Incident date is required.';
    } elseif (!is_valid_report_date($incident_date)) {
        $errors['incident_date'] = 'Please enter a valid date.';
    } elseif (!is_date_in_range($incident_date, $min_incident_date, $max_incident_date)) {
        $errors['incident_date'] = 'Incident date must be within the last 100 years and not in the future.';
    }

    if ($location === '') {
        $errors['location'] = 'Location is required.';
    }

    if ($latitude !== '' || $longitude !== '') {
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            $errors['coordinates'] = 'Selected map coordinates are invalid.';
        } elseif ((float) $latitude < -90 || (float) $latitude > 90 || (float) $longitude < -180 || (float) $longitude > 180) {
            $errors['coordinates'] = 'Selected map coordinates are outside the valid range.';
        }
    }

    if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors['image'] = 'Please upload an image.';
    } elseif ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $errors['image'] = 'Image upload failed. Please try again.';
    } elseif ($_FILES['image']['size'] > $max_file_size) {
        $errors['image'] = 'Image size must not be more than 5MB.';
    }

    $image_path = '';

    if (!isset($errors['image'])) {
        $original_name = $_FILES['image']['name'];
        $tmp_name = $_FILES['image']['tmp_name'];
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $image_info = @getimagesize($tmp_name);
        $mime_type = $image_info['mime'] ?? '';

        if (!is_uploaded_file($tmp_name)) {
            $errors['image'] = 'Invalid upload request.';
        } elseif (!in_array($extension, $allowed_extensions, true)) {
            $errors['image'] = 'Only JPG, JPEG, and PNG files are allowed.';
        } elseif (!in_array($mime_type, $allowed_mime_types, true)) {
            $errors['image'] = 'Invalid image type.';
        } else {
            $base_name = pathinfo($original_name, PATHINFO_FILENAME);
            $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $base_name);
            $safe_name = trim($safe_name, '_');

            if ($safe_name === '') {
                $safe_name = 'image';
            }

            $new_file_name = uniqid('report_', true) . '_' . $safe_name . '.' . $extension;
            $upload_dir = __DIR__ . '/uploads/';
            $target_path = $upload_dir . $new_file_name;

            if (!is_dir($upload_dir)) {
                $errors['image'] = 'Upload folder does not exist.';
            } elseif (!is_writable($upload_dir)) {
                $errors['image'] = 'Upload folder is not writable.';
            } elseif (!move_uploaded_file($tmp_name, $target_path)) {
                $errors['image'] = 'Unable to save uploaded image.';
            } else {
                $image_path = 'uploads/' . $new_file_name;
            }
        }
    }

    if (empty($errors)) {
        $status = 'Pending';
        $user_id = (int) $_SESSION['user_id'];
        $latitude_value = $latitude === '' ? null : $latitude;
        $longitude_value = $longitude === '' ? null : $longitude;
        $sql = 'INSERT INTO reports (user_id, title, description, category, incident_date, location, image, status, latitude, longitude)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'isssssssss', $user_id, $title, $description, $category, $incident_date, $location, $image_path, $status, $latitude_value, $longitude_value);

            if (mysqli_stmt_execute($stmt)) {
                add_status_history($conn, (int) mysqli_insert_id($conn), $status, 'Report submitted.');
                set_flash_message('success', 'Your report has been submitted successfully.');
                mysqli_stmt_close($stmt);
                redirect('/complaint-system/my_reports.php');
            } else {
                error_log('Report insert failed: ' . mysqli_stmt_error($stmt));
                $errors['general'] = 'Unable to save report. Please try again.';

                if ($image_path !== '' && file_exists(__DIR__ . '/' . $image_path)) {
                    unlink(__DIR__ . '/' . $image_path);
                }
            }

            mysqli_stmt_close($stmt);
        } else {
            error_log('Report insert prepare failed: ' . mysqli_error($conn));
            $errors['general'] = 'Database error. Please try again.';

            if ($image_path !== '' && file_exists(__DIR__ . '/' . $image_path)) {
                unlink(__DIR__ . '/' . $image_path);
            }
        }
    }
}

include 'includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card app-card">
                    <div class="card-body p-4">
                        <h1 class="h3 mb-3">Submit Report</h1>
                        <p class="text-muted mb-4">Fill in the complaint details and upload a supporting image.</p>

                        <?php if (isset($errors['general'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($errors['general']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="submit_report.php" enctype="multipart/form-data" novalidate>
                            <?php csrf_field(); ?>
                            <div class="form-steps">
                                <div class="form-step-indicator active" data-step="1">
                                    <span class="step-index">1</span>
                                    <span class="step-label">Details</span>
                                </div>
                                <div class="form-step-indicator" data-step="2">
                                    <span class="step-index">2</span>
                                    <span class="step-label">Location</span>
                                </div>
                                <div class="form-step-indicator" data-step="3">
                                    <span class="step-index">3</span>
                                    <span class="step-label">Evidence</span>
                                </div>
                            </div>

                            <div class="form-step" data-step="1">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Title</label>
                                    <div class="field-control">
                                        <input type="text" class="form-control field-input <?php echo isset($errors['title']) ? 'is-invalid' : ''; ?>" id="title" name="title" value="<?php echo sanitize_input($title); ?>" placeholder="Brief summary of the complaint" required>
                                        <span class="validation-warn" aria-hidden="true">!</span>
                                        <span class="validation-tick" aria-hidden="true">&#10003;</span>
                                    </div>
                                    <?php if (isset($errors['title'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['title']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <div class="field-control field-control-textarea">
                                        <textarea class="form-control field-input <?php echo isset($errors['description']) ? 'is-invalid' : ''; ?>" id="description" name="description" rows="6" placeholder="Describe what happened and any important details" required><?php echo sanitize_input($description); ?></textarea>
                                        <span class="validation-warn" aria-hidden="true">!</span>
                                        <span class="validation-tick" aria-hidden="true">&#10003;</span>
                                    </div>
                                    <?php if (isset($errors['description'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['description']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="category" class="form-label">Category</label>
                                        <div class="field-control">
                                            <select class="form-select field-input <?php echo isset($errors['category']) ? 'is-invalid' : ''; ?>" id="category" name="category" required>
                                                <option value="">Select category</option>
                                                <?php foreach ($categories as $option): ?>
                                                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $category === $option ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($option); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <span class="validation-warn" aria-hidden="true">!</span>
                                            <span class="validation-tick" aria-hidden="true">&#10003;</span>
                                        </div>
                                        <?php if (isset($errors['category'])): ?>
                                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['category']); ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="incident_date" class="form-label">Incident Date</label>
                                        <div class="field-control">
                                            <input type="date" class="form-control field-input <?php echo isset($errors['incident_date']) ? 'is-invalid' : ''; ?>" id="incident_date" name="incident_date" value="<?php echo sanitize_input($incident_date); ?>" min="<?php echo $min_incident_date; ?>" max="<?php echo $max_incident_date; ?>" required>
                                            <span class="validation-warn" aria-hidden="true">!</span>
                                            <span class="validation-tick" aria-hidden="true">&#10003;</span>
                                        </div>
                                        <?php if (isset($errors['incident_date'])): ?>
                                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['incident_date']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="form-step d-none" data-step="2">
                                <div class="mb-3">
                                    <label class="form-label">Incident Location on Map</label>
                                    <p class="form-text mb-2">Click map to select incident location. Click again to move the marker.</p>
                                    <div class="map-tools p-3 mb-3">
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-7">
                                                <label for="location_search" class="form-label small mb-1">Search Location</label>
                                                <input type="text" class="form-control" id="location_search" placeholder="Search location (e.g. Yogyakarta)">
                                            </div>
                                            <div class="col-md-5">
                                                <div class="d-grid d-sm-flex gap-2">
                                                    <button type="button" class="btn btn-outline-primary" id="search_location_button">Search</button>
                                                    <button type="button" class="btn btn-outline-secondary" id="current_location_button">Use My Current Location</button>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="map_message" class="alert d-none mt-3 mb-0" role="alert"></div>
                                    </div>
                                    <div id="incident_map" class="map-panel"></div>
                                    <input type="hidden" id="latitude" name="latitude" value="<?php echo sanitize_input($latitude); ?>">
                                    <input type="hidden" id="longitude" name="longitude" value="<?php echo sanitize_input($longitude); ?>">
                                    <?php if (isset($errors['coordinates'])): ?>
                                        <div class="text-danger small mt-2"><?php echo htmlspecialchars($errors['coordinates']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label for="location" class="form-label">Location</label>
                                    <div class="field-control">
                                        <input type="text" class="form-control field-input <?php echo isset($errors['location']) ? 'is-invalid' : ''; ?>" id="location" name="location" value="<?php echo sanitize_input($location); ?>" placeholder="Street, building, or public area" required>
                                        <span class="validation-warn" aria-hidden="true">!</span>
                                        <span class="validation-tick" aria-hidden="true">&#10003;</span>
                                    </div>
                                    <?php if (isset($errors['location'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['location']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="form-step d-none" data-step="3">
                                <div class="mb-3">
                                    <label for="image" class="form-label">Image Evidence</label>
                                    <div class="field-control">
                                        <input type="file" class="form-control field-input <?php echo isset($errors['image']) ? 'is-invalid' : ''; ?>" id="image" name="image" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required>
                                        <span class="validation-warn" aria-hidden="true">!</span>
                                        <span class="validation-tick" aria-hidden="true">&#10003;</span>
                                    </div>
                                    <div class="form-text">Allowed: JPG, JPEG, PNG. Maximum size: 5MB.</div>
                                    <?php if (isset($errors['image'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['image']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="image-preview d-none" id="image_preview">
                                    <img src="" alt="Selected evidence preview" class="image-preview-img">
                                </div>
                            </div>

                            <div class="form-step-actions">
                                <button type="button" class="btn btn-outline-secondary" id="prev_step">Back</button>
                                <div class="form-actions">
                                    <a href="my_reports.php" class="btn btn-outline-secondary">My Reports</a>
                                    <button type="button" class="btn btn-primary" id="next_step">Next</button>
                                    <button type="submit" class="btn btn-primary d-none" id="submit_report">Submit Report</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const savedLatitude = <?php echo $latitude !== '' ? json_encode((float) $latitude) : 'null'; ?>;
    const savedLongitude = <?php echo $longitude !== '' ? json_encode((float) $longitude) : 'null'; ?>;
    const defaultCenter = [3.139, 101.6869];
    const mapCenter = savedLatitude !== null && savedLongitude !== null ? [savedLatitude, savedLongitude] : defaultCenter;
    const incidentMap = L.map('incident_map').setView(mapCenter, savedLatitude !== null ? 14 : 12);
    let incidentMarker = null;
    const mapMessage = document.getElementById('map_message');
    const searchInput = document.getElementById('location_search');
    const searchButton = document.getElementById('search_location_button');
    const currentLocationButton = document.getElementById('current_location_button');
    const locationInput = document.getElementById('location');
    const stepIndicators = document.querySelectorAll('.form-step-indicator');
    const steps = document.querySelectorAll('.form-step');
    const prevStepButton = document.getElementById('prev_step');
    const nextStepButton = document.getElementById('next_step');
    const submitButton = document.getElementById('submit_report');
    const imageInput = document.getElementById('image');
    const imagePreview = document.getElementById('image_preview');
    const imagePreviewImg = imagePreview ? imagePreview.querySelector('img') : null;
    let currentStep = 1;
    const fieldRules = {
        title: function (value) {
            return value.length >= 5 && value.length <= 150;
        },
        description: function (value) {
            return value.length >= 10;
        },
        category: function (value) {
            return value !== '';
        },
        incident_date: function (value) {
            if (value === '') {
                return false;
            }

            const minDate = document.getElementById('incident_date').getAttribute('min');
            const maxDate = document.getElementById('incident_date').getAttribute('max');

            return value >= minDate && value <= maxDate;
        },
        location: function (value) {
            return value.length > 0;
        },
        image: function () {
            const fileInput = document.getElementById('image');
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                return false;
            }

            const file = fileInput.files[0];
            const allowedTypes = ['image/jpeg', 'image/png'];
            const maxSize = 5 * 1024 * 1024;

            return allowedTypes.includes(file.type) && file.size <= maxSize;
        }
    };

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(incidentMap);

    function setIncidentMarker(lat, lng) {
        document.getElementById('latitude').value = lat.toFixed(8);
        document.getElementById('longitude').value = lng.toFixed(8);

        if (incidentMarker) {
            incidentMarker.setLatLng([lat, lng]);
        } else {
            incidentMarker = L.marker([lat, lng]).addTo(incidentMap);
        }
    }

    function showMapMessage(message, type) {
        mapMessage.className = 'alert alert-' + type + ' mt-3 mb-0';
        mapMessage.textContent = message;
    }

    function clearMapMessage() {
        mapMessage.className = 'alert d-none mt-3 mb-0';
        mapMessage.textContent = '';
    }

    function moveMapToLocation(lat, lng, zoom) {
        incidentMap.flyTo([lat, lng], zoom || 15, {
            duration: .6
        });
        setIncidentMarker(lat, lng);
    }

    function updateLocationInput(name) {
        if (!name) {
            return;
        }

        locationInput.value = name;
        validateField(locationInput);
    }

    function showStep(step) {
        currentStep = step;
        steps.forEach((item) => {
            item.classList.toggle('d-none', parseInt(item.dataset.step, 10) !== step);
        });
        stepIndicators.forEach((item) => {
            const itemStep = parseInt(item.dataset.step, 10);
            item.classList.toggle('active', itemStep === step);
            item.classList.toggle('completed', itemStep < step);
        });

        if (prevStepButton) {
            prevStepButton.disabled = step === 1;
        }

        if (nextStepButton && submitButton) {
            const isLast = step === steps.length;
            nextStepButton.classList.toggle('d-none', isLast);
            submitButton.classList.toggle('d-none', !isLast);
        }

        if (step === 2 && incidentMap) {
            setTimeout(() => {
                incidentMap.invalidateSize();
            }, 200);
        }
    }

    function validateStep(step) {
        const stepFields = {
            1: ['title', 'description', 'category', 'incident_date'],
            2: ['location'],
            3: ['image']
        };
        const fields = stepFields[step] || [];
        let valid = true;

        fields.forEach((fieldId) => {
            const field = document.getElementById(fieldId);
            if (!field) {
                return;
            }
            field.dataset.touched = 'true';
            validateField(field);
            if (!field.classList.contains('is-valid')) {
                valid = false;
            }
        });

        return valid;
    }

    function validateField(field) {
        const rule = fieldRules[field.id];

        if (!rule) {
            return;
        }

        const value = field.value.trim();
        const isValid = rule(value);

        field.classList.toggle('is-valid', isValid);
        field.classList.toggle('is-pending', field.dataset.touched === 'true' && !isValid);
        if (isValid) {
            field.classList.remove('is-invalid');
        }

        if (field.id === 'incident_date') {
            field.setCustomValidity(isValid ? '' : 'Incident date must be within the last 100 years and not in the future.');
        }
    }

    Object.keys(fieldRules).forEach(function (fieldId) {
        const field = document.getElementById(fieldId);

        if (!field) {
            return;
        }

        const handler = function () {
            field.dataset.touched = 'true';
            validateField(field);
        };

        field.addEventListener('input', handler);
        field.addEventListener('change', handler);
        validateField(field);
    });

    if (prevStepButton) {
        prevStepButton.addEventListener('click', function () {
            showStep(Math.max(1, currentStep - 1));
        });
    }

    if (nextStepButton) {
        nextStepButton.addEventListener('click', function () {
            if (validateStep(currentStep)) {
                showStep(Math.min(steps.length, currentStep + 1));
            } else {
                showMapMessage('Please complete the required fields before continuing.', 'warning');
            }
        });
    }

    if (imageInput) {
        imageInput.addEventListener('change', function () {
            if (imageInput.files && imageInput.files[0] && imagePreview && imagePreviewImg) {
                const reader = new FileReader();
                reader.onload = function (event) {
                    imagePreviewImg.src = event.target.result;
                    imagePreview.classList.remove('d-none');
                };
                reader.readAsDataURL(imageInput.files[0]);
            }
        });
    }

    showStep(currentStep);

    function reverseGeocode(lat, lng) {
        fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lng), {
            headers: {
                'Accept': 'application/json'
            }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Reverse geocoding failed.');
                }

                return response.json();
            })
            .then(function (data) {
                updateLocationInput(data.display_name || '');
            })
            .catch(function () {
                showMapMessage('Unable to fetch the location name. You can type it manually.', 'warning');
            });
    }

    if (savedLatitude !== null && savedLongitude !== null) {
        setIncidentMarker(savedLatitude, savedLongitude);
    }

    incidentMap.on('click', function (event) {
        clearMapMessage();
        setIncidentMarker(event.latlng.lat, event.latlng.lng);
        reverseGeocode(event.latlng.lat, event.latlng.lng);
    });

    currentLocationButton.addEventListener('click', function () {
        clearMapMessage();

        if (!navigator.geolocation) {
            showMapMessage('Your browser does not support current location detection.', 'warning');
            return;
        }

        currentLocationButton.disabled = true;
        currentLocationButton.textContent = 'Locating...';

        navigator.geolocation.getCurrentPosition(
            function (position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                moveMapToLocation(lat, lng, 16);
                reverseGeocode(lat, lng);
                showMapMessage('Current location selected. You can click the map to adjust it.', 'success');
                currentLocationButton.disabled = false;
                currentLocationButton.textContent = 'Use My Current Location';
            },
            function (error) {
                let message = 'Unable to get your current location.';

                if (error.code === error.PERMISSION_DENIED) {
                    message = 'Location permission was denied. You can still search or click the map manually.';
                } else if (error.code === error.POSITION_UNAVAILABLE) {
                    message = 'Your current location is unavailable. Try searching by keyword instead.';
                } else if (error.code === error.TIMEOUT) {
                    message = 'Location request timed out. Please try again or select the location manually.';
                }

                showMapMessage(message, 'warning');
                currentLocationButton.disabled = false;
                currentLocationButton.textContent = 'Use My Current Location';
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 60000
            }
        );
    });

    searchButton.addEventListener('click', function () {
        const query = searchInput.value.trim();
        clearMapMessage();

        if (query === '') {
            showMapMessage('Please enter a location keyword before searching.', 'warning');
            return;
        }

        searchButton.disabled = true;
        searchButton.textContent = 'Searching...';

        fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(query), {
            headers: {
                'Accept': 'application/json'
            }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Search request failed.');
                }

                return response.json();
            })
            .then(function (results) {
                if (!Array.isArray(results) || results.length === 0) {
                    showMapMessage('No location found. Try a more specific keyword.', 'warning');
                    return;
                }

                const lat = parseFloat(results[0].lat);
                const lng = parseFloat(results[0].lon);

                if (Number.isNaN(lat) || Number.isNaN(lng)) {
                    showMapMessage('Search result did not include valid coordinates.', 'warning');
                    return;
                }

                moveMapToLocation(lat, lng, 15);
                updateLocationInput(results[0].display_name || query);
                showMapMessage('Location selected from search result. You can click the map to adjust it.', 'success');
            })
            .catch(function () {
                showMapMessage('Location search is temporarily unavailable. Please try again later or click the map manually.', 'danger');
            })
            .finally(function () {
                searchButton.disabled = false;
                searchButton.textContent = 'Search';
            });
    });

    searchInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            searchButton.click();
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
