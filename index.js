/* =========================
   Hero Slider Functionality
========================= */
let slideIndex = 1;
let slideInterval;
let isHeroHovered = false; 

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
    let prevBtn = document.querySelector(".prev");
    let nextBtn = document.querySelector(".next");
    
    if (n > slides.length) { slideIndex = 1; }
    if (n < 1) { slideIndex = slides.length; }
    
    for (i = 0; i < slides.length; i++) {
        slides[i].classList.remove("active");
    }
    for (i = 0; i < dots.length; i++) {
        dots[i].classList.remove("active");
    }
    
    if (slides.length > 0) {
        slides[slideIndex - 1].classList.add("active");
    }
    if (dots.length > 0) {
        dots[slideIndex - 1].classList.add("active");
    }

    // Dynamic visibility for navigation arrows
    if (prevBtn && nextBtn) {
        if (slideIndex === 1) {
            prevBtn.style.display = "none";
        } else {
            prevBtn.style.display = "flex"; 
        }

        if (slideIndex === slides.length) {
            nextBtn.style.display = "none";
        } else {
            nextBtn.style.display = "flex";
        }
    }
}

function startSliderTimer() {
    clearInterval(slideInterval);
    slideInterval = setInterval(function() {
        // Only progress the slide if the user's mouse is NOT over the section
        if (!isHeroHovered) {
            showSlides(slideIndex += 1);
        }
    }, 5000); 
}

function resetSliderTimer() {
    clearInterval(slideInterval);
    startSliderTimer();
}

// ==========================================
// Strict Pause Slider on Hover
// ==========================================
const heroSection = document.querySelector('.hero-slider');

if (heroSection) {
    heroSection.addEventListener('mouseenter', () => {
        isHeroHovered = true;
    });
    
    heroSection.addEventListener('mouseleave', () => {
        isHeroHovered = false;
        resetSliderTimer(); 
    });
}

/* ========================================================
   Services True Infinite 3D Carousel (Absolute Math Engine)
======================================================== */
const carouselItems = document.querySelectorAll('.carousel-item');
const totalItems = carouselItems.length;

// absoluteFloatIndex counts to infinity (e.g. 0, 10, 100, 1000...) and never wraps.
let absoluteFloatIndex = 0;   
let targetAbsoluteIndex = null; 
let isHovered = false;        
let isModalOpen = false;  
let isPausedByClick = false; 
let clickPauseTimeout;       
const autoSpeed = 0.004;     

function renderCarousel() {
    if (totalItems === 0) return;

    // 1. Move the master absolute index
    if (targetAbsoluteIndex !== null) {
        // Smoothly glide towards the target if an arrow/item was clicked
        absoluteFloatIndex += (targetAbsoluteIndex - absoluteFloatIndex) * 0.08;
        
        // Lock it in place when it gets close enough
        if (Math.abs(targetAbsoluteIndex - absoluteFloatIndex) < 0.005) {
            absoluteFloatIndex = targetAbsoluteIndex;
            targetAbsoluteIndex = null; 
        }
    } else if (!isHovered && !isModalOpen && !isPausedByClick) {
        // Continuously drift if untouched and not temporarily paused
        absoluteFloatIndex += autoSpeed;
    }

    // 2. Normalize the absolute counter into a wrap-around base (0 to totalItems)
    let normalizedFloat = ((absoluteFloatIndex % totalItems) + totalItems) % totalItems;

    // 3. Position the images
    carouselItems.forEach((item, index) => {
        // Find the shortest path around the circle for this item
        let diff = index - normalizedFloat;
        
        if (diff > totalItems / 2) diff -= totalItems;
        if (diff < -totalItems / 2) diff += totalItems;
        
        let absDiff = Math.abs(diff);

        // 3D positioning mathematics
        let translateX = diff * 200;            
        let translateZ = -absDiff * 250;        
        let rotateY = -diff * 12;               
        let scale = Math.max(0.6, 1 - absDiff * 0.1); 
        
        // Fade out items that are further back
        let opacity = Math.max(0, 1 - absDiff * 0.35);
        
        // Ensure center items overlap the outer items
        let zIndex = Math.round(100 - absDiff * 10);

        // Uses proper template literals with backticks to apply the CSS dynamically
        item.style.transform = `translateX(${translateX}px) translateZ(${translateZ}px) rotateY(${rotateY}deg) scale(${scale})`;
        item.style.opacity = opacity;
        item.style.zIndex = zIndex;
        
        // Center highlighting
        if (absDiff < 0.3) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
    
    // Call next frame
    requestAnimationFrame(renderCarousel);
}

// Hover Event Listeners to pause rotation
const carouselContainer = document.getElementById('servicesCarousel');
if (carouselContainer) {
    carouselContainer.addEventListener('mouseenter', () => isHovered = true);
    carouselContainer.addEventListener('mouseleave', () => isHovered = false);
}

// Temporary Pause Function (Triggers on click)
function pauseCarouselTemporarily() {
    isPausedByClick = true;
    clearTimeout(clickPauseTimeout); 
    
    // Resume drifting after 5 seconds
    clickPauseTimeout = setTimeout(() => {
        isPausedByClick = false;
    }, 5000);
}

// Arrow Button Navigation (FIXED CENTERING LOGIC)
function navigateCarousel(direction) {
    // 1. Find exactly what integer we are closest to right now
    let currentCenter = Math.round(absoluteFloatIndex);
    if (targetAbsoluteIndex !== null) {
        currentCenter = targetAbsoluteIndex;
    }
    
    // 2. Add the direction. This guarantees target is a perfect integer (dead center).
    targetAbsoluteIndex = currentCenter + direction;
    pauseCarouselTemporarily();
}

// Handle clicking on specific items (FIXED CENTERING LOGIC)
function handleCarouselClick(clickedIndex, title, desc) {
    // 1. Find exactly what integer we are closest to right now
    let currentCenter = Math.round(absoluteFloatIndex);
    if (targetAbsoluteIndex !== null) {
        currentCenter = targetAbsoluteIndex;
    }

    // 2. What physical array item is currently at that center?
    let currentNormalized = ((currentCenter % totalItems) + totalItems) % totalItems;
    
    // 3. Find the shortest path from the current center to the clicked item
    let diff = clickedIndex - currentNormalized;
    if (diff > totalItems / 2) diff -= totalItems;
    if (diff < -totalItems / 2) diff += totalItems;

    // 4. If distance is 0, they clicked the center item, so open the modal
    if (diff === 0) {
        openModal(title, desc);
    } else {
        // Shift exactly by the integer difference
        targetAbsoluteIndex = currentCenter + diff;
        pauseCarouselTemporarily();
    }
}

// Boot up the carousel
document.addEventListener("DOMContentLoaded", () => {
    if (carouselItems.length > 0) {
        requestAnimationFrame(renderCarousel);
    }
});

/* =========================
   Modal Functionality
========================= */
const modal = document.getElementById("serviceModal");
const modalTitle = document.getElementById("modalTitle");
const modalDesc = document.getElementById("modalDesc");

function openModal(title, description) {
    modalTitle.innerText = title;
    modalDesc.innerText = description;
    modal.style.display = "block";
    isModalOpen = true; // Pauses carousel background movement
}

function closeModal() {
    modal.style.display = "none";
    isModalOpen = false; // Resumes carousel
}

// Close modal if user clicks outside of the box
window.onclick = function(event) {
    if (event.target == modal) {
        modal.style.display = "none";
        isModalOpen = false;
    }
}

/* =========================
   Back to Top Functionality
========================= */
const backToTopBtn = document.getElementById("backToTopBtn");

window.addEventListener("scroll", () => {
    if (window.scrollY > 300) {
        backToTopBtn.classList.add("show");
    } else {
        backToTopBtn.classList.remove("show");
    }
});

function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: "smooth"
    });
}