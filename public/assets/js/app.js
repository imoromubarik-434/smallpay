// Flash messages disappear by themselves after a few seconds
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.alert').forEach((box) => {
        setTimeout(() => box.remove(), 6000);
    });
});