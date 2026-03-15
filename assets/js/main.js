// assets/js/main.js

document.addEventListener('DOMContentLoaded', function() {
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
