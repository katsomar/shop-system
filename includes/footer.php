<?php
/**
 * Shared Footer Layout Template
 */

// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not permitted.');
}
?>
                    
                </div> <!-- End of fade-in container -->
            </main> <!-- End of main-content -->
            
            <!-- Standard App Footer -->
            <footer style="padding: 1.5rem 2rem; background-color: var(--bg-card); border-top: 1px solid var(--border-color); text-align: center; font-size: 0.75rem; color: var(--text-muted); transition: background-color var(--transition-normal), border-color var(--transition-normal);">
                <div class="d-flex justify-between align-center" style="max-width: 1200px; margin: 0 auto; flex-wrap: wrap; gap: 0.5rem;">
                    <span>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</span>
                    <span class="d-flex gap-3">
                        <a href="#" style="color: var(--text-muted);">Privacy Policy</a>
                        <span>&middot;</span>
                        <a href="#" style="color: var(--text-muted);">Terms of Service</a>
                    </span>
                </div>
            </footer>
            
        </div> <!-- End of main column flex container -->
    </div> <!-- End of app-container -->

    <!-- Global Application Scripts -->
    <script src="/shop-system/assets/js/app.js"></script>
    
    <!-- Render Page Specific Script Code if set -->
    <?php if (isset($page_scripts)): ?>
        <?php echo $page_scripts; ?>
    <?php endif; ?>
</body>
</html>
