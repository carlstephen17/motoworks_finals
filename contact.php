<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/image_upload.php';
generateCSRFToken();

// Generate CSRF Token for security
$configFile = __DIR__ . '/includes/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

$success_message = '';
$error_message = '';
$user_name = '';
$user_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? null)) {
        $error_message = 'Invalid security token. Please refresh and try again.';
    } else {
        $user_name = trim((string) ($_POST['name'] ?? ''));
        $user_email = trim((string) ($_POST['email'] ?? ''));
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));
        $imagePath = null;

        if ($user_name === '' || !filter_var($user_email, FILTER_VALIDATE_EMAIL) || $message === '' || mb_strlen($user_name) > 255 || mb_strlen($user_email) > 100 || mb_strlen($subject) > 255 || mb_strlen($message) > 10000) {
            $error_message = 'Please fill in all required fields.';
        } else {
            try {
                $imagePath = saveSiteImageUpload($_FILES['image'] ?? [], 'testimonials');
            } catch (RuntimeException $error) {
                $error_message = $error->getMessage();
            }

            // Save to Database
            if ($error_message === '' && isset($pdo)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO testimonial (testimonialName, testimonialEmail, testimonialSubject, testimonialMessage, testimonialImage, testimonialStatus, testimonialCreatedAt) VALUES (?, ?, ?, ?, ?, 'approved', NOW())");
                    $stmt->execute([$user_name, $user_email, $subject, $message, $imagePath]);

                    $success_message = 'Thank you! Your message and review have been submitted successfully.';
                    // Clear inputs on success
                    $user_name = '';
                    $user_email = '';
                } catch (PDOException $e) {
                    if ($imagePath !== null && !removeSiteTestimonialUpload($imagePath)) {
                        error_log('Unable to remove uploaded testimonial image after save failure: ' . $imagePath);
                    }
                    error_log('Contact form submission failed: ' . $e->getMessage());
                    $error_message = 'Unable to save your message right now. Please try again.';
                }
            } else {
                $error_message = 'Database connection not available.';
            }
        }
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => empty($error_message),
            'message' => !empty($error_message) ? $error_message : $success_message
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Bencalo MotoWorks</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles/index/style.css">
    <link rel="stylesheet" href="styles/index/layout.css">
    <link rel="stylesheet" href="styles/index/components.css">
    <link rel="stylesheet" href="styles/index/modals.css">
    <style>
        body {
            background-color: var(--color-bg, #121212);
            color: var(--color-text, #ffffff);
            font-family: 'Poppins', sans-serif;
        }

        .contact-hero {
            background: linear-gradient(135deg, #181818 0%, #222222 100%);
            color: #fff;
            padding: 60px 20px;
            text-align: center;
            border-radius: 12px;
            margin-bottom: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border: 1px solid var(--color-border, #2a2a2a);
        }

        .contact-hero h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 15px;
            color: var(--color-border);
        }

        .contact-hero p {
            color: var(--color-text-muted, #a0a0a0);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .contact-wrapper {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 40px;
            margin-bottom: 60px;
        }

        @media (max-width: 900px) {
            .contact-wrapper {
                grid-template-columns: 1fr;
            }
        }

        .contact-info-card,
        .contact-form-card {
            background: var(--color-card-bg, #1e1e1e);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            border-top: 4px solid var(--color-primary, #00bcd4);
            border-left: 1px solid var(--color-border, #2a2a2a);
            border-right: 1px solid var(--color-border, #2a2a2a);
            border-bottom: 1px solid var(--color-border, #2a2a2a);
        }

        .contact-info-card h3,
        .contact-form-card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--color-text, #fff);
            margin-bottom: 25px;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 25px;
        }

        .info-item i {
            font-size: 1.25rem;
            color: var(--color-text, #ffffff);
            background: rgba(0, 188, 212, 0.1);
            padding: 12px;
            border-radius: 50%;
            margin-right: 20px;
            box-shadow: 0 4px 10px rgba(0, 188, 212, 0.1);
            transition: all 0.3s ease;
        }

        .info-item:hover i {
            color: var(--color-primary, #00bcd4);
            background: rgba(0, 188, 212, 0.2);
        }

        .info-content h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--color-text, #fff);
            margin-bottom: 4px;
        }

        .info-content p {
            color: var(--color-text-muted, #a0a0a0);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--color-text, #e0e0e0);
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: #252525;
            border: 1px solid var(--color-border, #333);
            color: #fff;
            border-radius: 6px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--color-primary, #00bcd4);
            box-shadow: 0 0 0 3px rgba(0, 188, 212, 0.15);
            background: #2a2a2a;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }

        .btn-submit {
            background: var(--color-primary);
            color: var(--color-text);
            font-weight: 700;
            padding: 14px 28px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            width: 100%;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 188, 212, 0.2);
        }

        .btn-submit:hover {
            background: var(--color-border);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 188, 212, 0.3);
        }

        .alert-box {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 25px;
            font-size: 0.95rem;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            color: #75b798;
            border: 1px solid rgba(40, 167, 69, 0.4);
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.2);
            color: #ea868f;
            border: 1px solid rgba(220, 53, 69, 0.4);
        }

        .map-container {
            margin-top: 40px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            background: var(--color-card-bg, #1e1e1e);
            border: 1px solid var(--color-border, #2a2a2a);
            padding: 15px;
        }

        .map-container iframe {
            width: 100%;
            height: 400px;
            border: 0;
            border-radius: 8px;
            filter: grayscale(20%) contrast(110%);
        }
    </style>
</head>

<body>

    <?php require_once 'components/header.php'; ?>

    <main class="container" style="padding-top: 40px;">
        <div class="contact-hero">
            <h1>Get in Touch With Us</h1>
            <p>Have questions about our parts, services, or custom setups? Drop us a message or visit our shop in Bayawan City!</p>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert-box alert-success"><i class="fa-solid fa-circle-check" style="margin-right: 8px;"></i> <?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert-box alert-error"><i class="fa-solid fa-circle-exclamation" style="margin-right: 8px;"></i> <?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="contact-wrapper">
            <!-- Contact Details Card -->
            <div class="contact-info-card">
                <h3>Contact Information</h3>

                <div class="info-item">
                    <i class="fa-solid fa-location-dot"></i>
                    <div class="info-content">
                        <h4>Our Location</h4>
                        <p>Bollos Street, Boyco, Bayawan City, Negros Oriental, Philippines 6221</p>
                    </div>
                </div>

                <div class="info-item">
                    <i class="fa-solid fa-phone"></i>
                    <div class="info-content">
                        <h4>Phone Support</h4>
                        <p>(123)-456-7890</p>
                    </div>
                </div>

                <div class="info-item">
                    <i class="fa-solid fa-envelope"></i>
                    <div class="info-content">
                        <h4>Email Address</h4>
                        <p>bencalomotoworks@gmail.com</p>
                    </div>
                </div>

                <div class="info-item">
                    <i class="fa-solid fa-clock"></i>
                    <div class="info-content">
                        <h4>Business Hours</h4>
                        <p>Mon-Sat: 8:00 AM – 6:00 PM<br>Sun: CLOSED</p>
                    </div>
                </div>
            </div>

            <!-- Contact Form Card -->
            <div class="contact-form-card">
                <h3>Send Us a Message</h3>
                <form action="contact.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" class="form-control"
                            value="<?= htmlspecialchars($user_name) ?>"
                            required placeholder="Enter your full name">
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control"
                            value="<?= htmlspecialchars($user_email) ?>"
                            required placeholder="Enter your email address">
                    </div>

                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" class="form-control" required placeholder="What is this regarding?">
                    </div>

                    <div class="form-group">
                        <label for="message">Message / Testimonial</label>
                        <textarea id="message" name="message" class="form-control" required placeholder="Type your message or review here... This will automatically feature in our testimonials!"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="image">Profile / Avatar Image <span style="color: #888; font-weight: normal;">(Optional)</span></label>
                        <input type="file" id="image" name="image" class="file-upload-input" accept="image/png, image/jpeg, image/webp">
                        <small style="color: #888; display: block; margin-top: 5px;">Accepted formats: JPG, PNG, WEBP</small>
                    </div>

                    <button type="submit" class="btn-submit">Send Message & Review</button>
                </form>
            </div>
        </div>

        <!-- Embedded Map Section -->
        <div class="map-container">
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3951.848834164165!2d122.805!3d9.366!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zOcKwMjInMDAuMCJOIDEyMsKwNDguMDAiRQ!5e0!3m2!1sen!2sph!4v1650000000000!5m2!1sen!2sph"
                allowfullscreen=""
                loading="lazy">
            </iframe>
        </div>
    </main>

    <?php
    require_once 'components/footer.php';
    require_once 'components/modals.php';
    ?>

</body>

</html>