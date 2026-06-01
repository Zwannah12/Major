// assets/js/main.js

(function() {
    function setThemeAttributes(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-bs-theme', theme);
        document.documentElement.classList.toggle('theme-dark', theme === 'dark');
        document.documentElement.classList.toggle('theme-light', theme === 'light');

        if (document.body) {
            document.body.setAttribute('data-theme', theme);
            document.body.setAttribute('data-bs-theme', theme);
            document.body.classList.toggle('theme-dark', theme === 'dark');
            document.body.classList.toggle('theme-light', theme === 'light');
        }
    }

    function readSavedTheme() {
        try {
            return localStorage.getItem('agrosphere-theme');
        } catch (error) {
            return null;
        }
    }

    const savedTheme = readSavedTheme();
    const systemTheme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    setThemeAttributes(savedTheme || systemTheme);
})();

document.addEventListener('DOMContentLoaded', function() {
    const pageName = (window.location.pathname.split('/').pop() || 'index.php').replace('.php', '').replace(/[^a-z0-9_-]/gi, '-').toLowerCase();
    document.body.classList.add('page-' + pageName);

    if (window.location.pathname.includes('/dashboard/')) {
        document.body.classList.add('page-dashboard');
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-bs-theme', theme);
        document.documentElement.classList.toggle('theme-dark', theme === 'dark');
        document.documentElement.classList.toggle('theme-light', theme === 'light');
        document.body.setAttribute('data-theme', theme);
        document.body.setAttribute('data-bs-theme', theme);
        document.body.classList.toggle('theme-dark', theme === 'dark');
        document.body.classList.toggle('theme-light', theme === 'light');

        try {
            localStorage.setItem('agrosphere-theme', theme);
        } catch (error) {
            // Theme still applies for this page even when storage is unavailable.
        }

        const icon = document.querySelector('#themeToggle .theme-toggle-icon');
        if (icon) {
            icon.textContent = theme === 'dark' ? '☀' : '◐';
            icon.setAttribute('aria-hidden', 'true');
        }

        const label = document.querySelector('#themeToggle .theme-toggle-label');
        if (label) {
            label.textContent = theme === 'dark' ? 'Light' : 'Dark';
        }

        const toggle = document.querySelector('#themeToggle');
        if (toggle) {
            toggle.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
            toggle.setAttribute('title', theme === 'dark' ? 'Light mode' : 'Dark mode');
        }
    }

    function addThemeToggle() {
        if (document.querySelector('#themeToggle')) {
            applyTheme(document.documentElement.getAttribute('data-theme') || 'light');
            return;
        }

        const target = document.querySelector('.navbar .ms-auto') || document.querySelector('.navbar .navbar-nav');

        const button = document.createElement('button');
        button.type = 'button';
        button.id = 'themeToggle';
        button.className = 'btn btn-outline-success theme-toggle';
        button.innerHTML = '<span class="theme-toggle-icon" aria-hidden="true">◐</span><span class="theme-toggle-label">Dark</span>';
        button.addEventListener('click', function() {
            const nextTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            applyTheme(nextTheme);
        });

        if (!target) {
            button.classList.add('theme-toggle-floating');
            document.body.appendChild(button);
        } else if (target.classList.contains('navbar-nav')) {
            const item = document.createElement('li');
            item.className = 'nav-item ms-2';
            item.appendChild(button);
            target.appendChild(item);
        } else {
            button.classList.add('me-2');
            target.prepend(button);
        }

        applyTheme(document.documentElement.getAttribute('data-theme') || 'light');
    }

    addThemeToggle();

    // Animated number counter for landing page stats
    const animatedNumbers = document.querySelectorAll('.animated-number');
    animatedNumbers.forEach(num => {
        const target = parseInt(num.getAttribute('data-target'));
        let current = 0;
        const increment = target / 200; // Adjust speed here

        const updateCounter = () => {
            if (current < target) {
                current += increment;
                num.textContent = Math.floor(current);
                requestAnimationFrame(updateCounter);
            } else {
                num.textContent = target;
            }
        };
        updateCounter();
    });

    // Sidebar Toggle (for dashboards) - requires jQuery
    // This is already included in each dashboard page for now, but keeping a general one here.
    // If you uncomment, ensure jQuery is loaded before this script globally.
    // $("#menu-toggle").click(function(e) {
    //     e.preventDefault();
    //     $("#wrapper").toggleClass("toggled");
    // });
});
