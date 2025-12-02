// public/js/viewComments.js
function toggleComments(blogId) {
    const commentsDiv = document.getElementById('comments-' + blogId); // get comments div
    const toggleBtn = document.getElementById('toggle-btn-' + blogId); // get toggle button

    if (commentsDiv.style.display === 'none' || commentsDiv.style.display === '') { // if comments are hidden
        commentsDiv.style.display = 'block'; // show comments
        toggleBtn.textContent = 'Hide Comments'; // change button text to "Hide Comments"
    } else { // if comments are visible
        commentsDiv.style.display = 'none'; // hide comments
        toggleBtn.textContent = 'View Comments'; // change button text to "View Comments"
    }
}
function toggleAnalytics() {
    const analyticsDiv = document.getElementById('analytics-section'); // get analytics section
    const analyticstoggleBtn = document.getElementById('analytics-toggle-btn'); // get toggle button

    if (analyticsDiv.style.display === 'none' || analyticsDiv.style.display === '') { // if analytics are hidden
        analyticsDiv.style.display = 'block'; // show analytics
        analyticstoggleBtn.textContent = 'Hide Analytics'; // change button text to "Hide Analytics"
    } else { // if analytics are visible
        analyticsDiv.style.display = 'none'; // hide analytics
        analyticstoggleBtn.textContent = 'View Analytics'; // change button text to "View Analytics"
    }
}