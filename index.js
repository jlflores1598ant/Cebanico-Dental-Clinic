let slideIndex = 1;
let slideInterval;

// Initialize slider
showSlides(slideIndex);
startSliderTimer();

function changeSlide(n) {
    showSlides(slideIndex += n);
    resetSliderTimer(); 
}

function currentSlide(n) {
    showSlides(slideIndex = n);
    resetSliderTimer();
}

function showSlides(n) {
    let i;
    let slides = document.getElementsByClassName("slide");
    let dots = document.getElementsByClassName("dot");
    
    // Loop back to first or last slide
    if (n > slides.length) { slideIndex = 1; }
    if (n < 1) { slideIndex = slides.length; }
    
    // Reset active classes
    for (i = 0; i < slides.length; i++) {
        slides[i].classList.remove("active");
    }
    for (i = 0; i < dots.length; i++) {
        dots[i].classList.remove("active");
    }
    
    // Set active slide and dot
    if (slides.length > 0) {
        slides[slideIndex - 1].classList.add("active");
    }
    if (dots.length > 0) {
        dots[slideIndex - 1].classList.add("active");
    }
}

function startSliderTimer() {
    slideInterval = setInterval(function() {
        showSlides(slideIndex += 1);
    }, 5000); 
}

function resetSliderTimer() {
    clearInterval(slideInterval);
    startSliderTimer();
}