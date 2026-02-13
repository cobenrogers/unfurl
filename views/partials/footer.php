<?php
/**
 * Footer Partial - Page Bottom & Scripts
 *
 * Closes main content, displays footer info, and loads JavaScript.
 */

use Unfurl\Security\OutputEscaper;

$escaper = new OutputEscaper();
?>
    </main>

    <!-- Footer -->
    <footer class="footer mt-10 py-6 border-t" role="contentinfo">
        <div class="container">
            <div class="grid md:grid-cols-3 gap-6">
                <!-- Footer Info -->
                <div class="footer-section">
                    <h3 style="font-weight: 600; margin-bottom: var(--space-2);">Unfurl</h3>
                    <p style="font-size: 0.875rem; color: var(--color-text-muted); margin-bottom: var(--space-2);">
                        Google News URL decoder and RSS feed generator for content aggregation and AI processing.
                    </p>
                </div>

                <!-- Quick Links -->
                <div class="footer-section">
                    <h4 style="font-weight: 600; margin-bottom: var(--space-2); font-size: 0.875rem;">Quick Links</h4>
                    <ul style="list-style: none; padding: 0;">
                        <li style="margin-bottom: var(--space-1);">
                            <a href="/feeds" style="color: var(--color-primary); text-decoration: none; font-size: 0.875rem;">Feeds</a>
                        </li>
                        <li style="margin-bottom: var(--space-1);">
                            <a href="/articles" style="color: var(--color-primary); text-decoration: none; font-size: 0.875rem;">Articles</a>
                        </li>
                        <li style="margin-bottom: var(--space-1);">
                            <a href="/settings" style="color: var(--color-primary); text-decoration: none; font-size: 0.875rem;">Settings</a>
                        </li>
                    </ul>
                </div>

                <!-- Footer Meta -->
                <div class="footer-section">
                    <h4 style="font-weight: 600; margin-bottom: var(--space-2); font-size: 0.875rem;">About</h4>
                    <p style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: var(--space-1);">
                        Built with security and simplicity in mind.
                    </p>
                    <p style="font-size: 0.8125rem; color: var(--color-text-muted);">
                        &copy; <?= date('Y') ?> BennernetLLC. All rights reserved.
                    </p>
                </div>
            </div>

            <hr style="margin: var(--space-5) 0; border: none; border-top: 1px solid var(--color-border);">

            <!-- Footer Bottom -->
            <div class="footer-bottom flex justify-between items-center text-sm" style="color: var(--color-text-muted); font-size: 0.8125rem;">
                <div>Unfurl v1.0.0</div>
                <div flex gap-3>
                    <a href="#" style="color: var(--color-text-muted); text-decoration: none;">Privacy</a>
                    <a href="#" style="color: var(--color-text-muted); text-decoration: none;">Terms</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript Libraries & Utilities -->
    <script type="module">
        // Import utility modules
        import { DOM, StringUtils } from '/assets/js/utils.js';
        import { Notify } from '/assets/js/notifications.js';
        import { FormValidator, FormSerializer, CSRFToken } from '/assets/js/forms.js';
        import { API } from '/assets/js/api.js';

        // Auto-dismiss flash messages after 5 seconds
        const flashMessages = document.getElementById('flash-messages');
        if (flashMessages) {
            setTimeout(() => {
                const alerts = flashMessages.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.transition = 'opacity 300ms ease-out';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                });
            }, 5000);
        }

        // Make utilities globally accessible for inline scripts if needed
        window.DOM = DOM;
        window.StringUtils = StringUtils;
        window.Notify = Notify;
        window.FormValidator = FormValidator;
        window.FormSerializer = FormSerializer;
        window.CSRFToken = CSRFToken;
        window.API = API;
    </script>

    <!-- Analytics (placeholder) -->
    <!-- Insert analytics script here if needed -->

    <!-- Confirm Modal -->
    <?php include __DIR__ . '/confirm-modal.php'; ?>
</body>
</html>
