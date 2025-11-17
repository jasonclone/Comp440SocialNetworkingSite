// verifyDualInputs.js

// Dual-tag validation
document.querySelector('form[action="../controllers/analytics.php"][name="dual_tag_form"]')?.addEventListener('submit', function (e) {
    const tag1 = this.querySelector('input[name="tag1"]').value.trim().toLowerCase();
    const tag2 = this.querySelector('input[name="tag2"]').value.trim().toLowerCase();
    if (tag1 && tag2 && tag1 === tag2) {
        alert("Tag 1 and Tag 2 cannot be the same.");
        e.preventDefault();
    }
});

// Users followed by both X and Y validation
document.querySelector('form[action="../controllers/analytics.php"][name="followed_form"]')?.addEventListener('submit', function (e) {
    const userX = this.querySelector('input[name="user_x"]').value.trim().toLowerCase();
    const userY = this.querySelector('input[name="user_y"]').value.trim().toLowerCase();
    if (userX && userY && userX === userY) {
        alert("User X and User Y cannot be the same.");
        e.preventDefault();
    }
});
