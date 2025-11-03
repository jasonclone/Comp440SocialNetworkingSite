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
