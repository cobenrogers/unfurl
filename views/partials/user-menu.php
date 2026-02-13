<?php
/**
 * User Menu Partial
 *
 * Displays user profile and logout button
 * Requires $auth variable to be passed from controller
 */
$user = $auth->getCurrentUser();
?>
<?php if ($user): ?>
<div class="user-menu" style="position: relative; display: inline-block;">
    <button id="user-menu-btn" class="user-avatar" style="
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: 2px solid #e2e8f0;
        font-weight: 600;
        font-size: 16px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    ">
        <?= htmlspecialchars(strtoupper(substr($user['name'], 0, 1))) ?>
    </button>

    <div id="user-dropdown" class="user-dropdown" style="display: none; position: absolute; right: 0; top: 48px; background: white; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 260px; z-index: 1000;">
        <div class="user-info" style="padding: 16px; border-bottom: 1px solid #e2e8f0;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                <div style="
                    width: 48px;
                    height: 48px;
                    border-radius: 50%;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: 600;
                    font-size: 20px;
                ">
                    <?= htmlspecialchars(strtoupper(substr($user['name'], 0, 1))) ?>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <strong style="display: block; margin-bottom: 4px; font-size: 14px; color: #1a202c;">
                        <?= htmlspecialchars($user['name']) ?>
                    </strong>
                    <small style="display: block; color: #718096; font-size: 13px; overflow: hidden; text-overflow: ellipsis;">
                        <?= htmlspecialchars($user['email']) ?>
                    </small>
                </div>
            </div>
            <?php if ($user['is_admin']): ?>
                <span class="badge" style="
                    display: inline-block;
                    padding: 4px 8px;
                    background: linear-gradient(135deg, #9f7aea 0%, #805ad5 100%);
                    color: white;
                    border-radius: 4px;
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                ">
                    <svg style="width: 12px; height: 12px; display: inline-block; vertical-align: middle; margin-right: 4px;" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    Admin
                </span>
            <?php endif; ?>
        </div>

        <div class="user-actions" style="padding: 8px 0;">
            <?php if ($user['is_admin']): ?>
                <a href="https://bennernet.com/auth/admin/" target="_blank" style="
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    padding: 10px 16px;
                    color: #2d3748;
                    text-decoration: none;
                    font-size: 14px;
                    transition: background 0.2s;
                " onmouseover="this.style.background='#f7fafc'" onmouseout="this.style.background='transparent'">
                    <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <span>Manage Users</span>
                </a>
            <?php endif; ?>
            <a href="/logout" style="
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 10px 16px;
                color: #e53e3e;
                text-decoration: none;
                font-size: 14px;
                transition: background 0.2s;
            " onmouseover="this.style.background='#fff5f5'" onmouseout="this.style.background='transparent'">
                <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                <span>Sign Out</span>
            </a>
        </div>
    </div>
</div>

<script>
(function() {
    const btn = document.getElementById('user-menu-btn');
    const dropdown = document.getElementById('user-dropdown');

    if (!btn || !dropdown) return;

    // Toggle dropdown
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
    });

    // Close when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.user-menu')) {
            dropdown.style.display = 'none';
        }
    });
})();
</script>
<?php endif; ?>
