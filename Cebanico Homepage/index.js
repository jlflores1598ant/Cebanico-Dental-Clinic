let slideIndex = 1;
let slideInterval;

// Initialize slider
showSlides(slideIndex);
startSliderTimer();

// Manual navigation
function changeSlide(n) {
    showSlides(slideIndex += n);
    resetSliderTimer(); 
}

function showSlides(n) {
    let i;
    let slides = document.getElementsByClassName("slide");
    
    // Loop back to first/last slide
    if (n > slides.length) { slideIndex = 1; }
    if (n < 1) { slideIndex = slides.length; }
    
    // Hide all slides
    for (i = 0; i < slides.length; i++) {
        slides[i].classList.remove("active");
    }
    
    // Show current slide
    if (slides.length > 0) {
        slides[slideIndex - 1].classList.add("active");
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