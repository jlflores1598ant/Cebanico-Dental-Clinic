/* =========================
   Hero Slider Functionality
========================= */
let slideIndex = 1;
let slideInterval;

// Initialize hero slider
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
    
    if (n > slides.length) { slideIndex = 1; }
    if (n < 1) { slideIndex = slides.length; }
    
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

/* ========================================================
   Services True Infinite 3D Carousel (Absolute Math Engine)
======================================================== */
const carouselItems = document.querySelectorAll('.carousel-item');
const totalItems = carouselItems.length;

// absoluteFloatIndex counts to infinity (e.g. 0, 10, 100, 1000...) and never wraps.
// This prevents ANY snapping or rewinding when traversing the edges.
let absoluteFloatIndex = 0;   
let targetAbsoluteIndex = null; 
let isHovered = false;        
let isModalOpen = false;  
let isPausedByClick = false; // Tracks if the user just clicked
let clickPauseTimeout;       // The timer for the pause
const autoSpeed = 0.004;     // Smooth continuous drift speed

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
        let translateX = diff * 200;            // Spread out horizontally
        let translateZ = -absDiff * 250;        // Push back into the screen
        let rotateY = -diff * 12;               // Angle them inwards
        let scale = Math.max(0.6, 1 - absDiff * 0.1); 
        
        // Fade out items that are further back
        let opacity = Math.max(0, 1 - absDiff * 0.35);
        
        // Ensure center items overlap the outer items
        let zIndex = Math.round(100 - absDiff * 10);

        // Apply styles without CSS transition for pure JS hardware acceleration
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
    clearTimeout(clickPauseTimeout); // Clear previous timer if they click multiple times fast
    
    // Resume drifting after 5 seconds (5000 milliseconds)
    clickPauseTimeout = setTimeout(() => {
        isPausedByClick = false;
    }, 5000);
}

// Arrow Button Navigation
function navigateCarousel(direction) {
    // Math.round ensures we calculate from the nearest whole integer, snapping the next item perfectly to the center
    let currentBase = (targetAbsoluteIndex !== null) ? targetAbsoluteIndex : Math.round(absoluteFloatIndex);
    targetAbsoluteIndex = currentBase + direction;
    
    pauseCarouselTemporarily(); // Trigger the 5-second pause
}

// Handle clicking on specific items
function handleCarouselClick(clickedIndex, title, desc) {
    let normalizedFloat = ((absoluteFloatIndex % totalItems) + totalItems) % totalItems;
    let diff = clickedIndex - normalizedFloat;
    
    // Shortest path around the circle
    if (diff > totalItems / 2) diff -= totalItems;
    if (diff < -totalItems / 2) diff += totalItems;

    // If they clicked the center-most item, open modal
    if (Math.abs(diff) < 0.4) {
        openModal(title, desc);
    } else {
        // Shift the carousel by the relative distance clicked. 
        // Math.round forces it to snap to a perfectly centered integer.
        let currentBase = (targetAbsoluteIndex !== null) ? targetAbsoluteIndex : absoluteFloatIndex;
        targetAbsoluteIndex = Math.round(currentBase + diff);
        
        pauseCarouselTemporarily(); // Trigger the 5-second pause
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