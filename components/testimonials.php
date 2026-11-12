<?php
// Ensure $testimonials is available even if included independently
if (!isset($testimonials)) {
    $testimonials = [
        ["name" => "Robert Smith", "location" => "Washington, D.C.", "text" => "Great service every time! Friendly and the mechanics always explain what they are doing. Trustworthy and reliable.", "image" => "images/testimonials/testimonial_1.png"],
        ["name" => "Emily Johnson", "location" => "San Francisco, California", "text" => "Saved me a lot of trouble! They diagnosed an engine issue that another shop couldn't fix. Excellent technical knowledge.", "image" => "images/testimonials/testimonial_2.png"],
        ["name" => "Alice Johnson", "location" => "Los Angeles, California", "text" => "Bencalo Motoworks is my go-to place for maintenance. They always use quality parts, and my car runs like a dream. Highly recommended!", "image" => "images/testimonials/testimonial_3.png"],
        ["name" => "John Doe", "location" => "Washington, D.C.", "text" => "Ordering parts through their website was seamless, and the delivery was quick. The parts are exact and exactly what I needed.", "image" => "images/testimonials/testimonial_4.png"]
    ];
}
?>
<!-- ==================== TESTIMONIALS SECTION ==================== -->
<section id="about" class="container section-padding-y">
    <div class="section-title center">
        <h2>HERE’S THE REASON WHY YOU SHOULD CHOOSE US</h2>
    </div>
    <div class="card-grid testimonial-grid">
        <?php foreach ($testimonials as $t): ?>
            <div class="testimonial-card">
                <div class="testimonial-header">
                    <img src="<?= htmlspecialchars($t['image']) ?>" alt="<?= htmlspecialchars($t['name']) ?>" class="testimonial-avatar">
                    <div class="testimonial-meta">
                        <h3 class="testimonial-name"><?= htmlspecialchars($t['name']) ?></h3>
                        <div class="testimonial-location"><?= htmlspecialchars($t['location']) ?></div>
                    </div>
                </div>
                <div class="stars">★★★★★</div>
                <p class="testimonial-text">"<?= htmlspecialchars($t['text']) ?>"</p>
            </div>
        <?php endforeach; ?>
    </div>
</section>