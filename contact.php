<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (isPost()) {
    verifyCsrf();
    flash('success', 'Thanks for reaching out. Your message has been noted for the Sprint 1 demo.');
    redirect('contact.php');
}

renderHeader('Contact');
?>
<section class="page-hero compact-hero">
    <p class="eyebrow">Contact Us</p>
    <h1>You Will Grow, You Will Succeed, We Promise That.</h1>
</section>

<section class="content-grid">
    <div class="panel">
        <h2>Contact Info</h2>
        <div class="stack">
            <div class="info-row"><strong>Call for inquiry</strong><span>+977 981-0000000</span></div>
            <div class="info-row"><strong>Send us email</strong><span>hello@jobnova.test</span></div>
            <div class="info-row"><strong>Opening hours</strong><span>Sun - Fri, 9 AM - 6 PM</span></div>
            <div class="info-row"><strong>Office</strong><span>Kathmandu, Nepal</span></div>
        </div>
    </div>
    <div class="panel contact-form-panel">
        <h2>Contact Info</h2>
        <form method="post" class="filters">
            <?= csrfField() ?>
            <label>
                Your name
                <input type="text" name="name" required>
            </label>
            <label>
                Email address
                <input type="email" name="email" required>
            </label>
            <label>
                Subject
                <input type="text" name="subject" required>
            </label>
            <label>
                Your message
                <textarea name="message" required></textarea>
            </label>
            <button type="submit">Send Message</button>
        </form>
    </div>
</section>
<?php renderFooter(); ?>
