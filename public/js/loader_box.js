document.addEventListener("DOMContentLoaded", function () {
    const loaderBox = document.querySelector('.loader-box');
    const hero = document.querySelector('.hero');
    const content = document.querySelector('.content');
    const about = document.querySelector('.about');

    setTimeout(() => {
        // Ẩn loader box sau thời gian quy định
        if (loaderBox) loaderBox.style.display = 'none';
        
        // Hiển thị các thành phần khác với hiệu ứng fade-in
        if (hero) hero.style.opacity = 1;
        if (content) content.style.opacity = 1;
        if (about) about.style.opacity = 1; // Thêm hiệu ứng fade-in cho about
    }, 1500); // Thời gian khớp với hiệu ứng
});
