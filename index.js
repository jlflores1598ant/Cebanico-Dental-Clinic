/* =========================
   Unified Global Script
========================= */
document.addEventListener("DOMContentLoaded", () => {
    
    // 1. Mobile Menu Toggle
    const hamburger = document.getElementById('hamburger');
    const navLinksContainer = document.getElementById('navLinks');
    const navLinksItems = document.querySelectorAll('.nav-links a');

    if (hamburger && navLinksContainer) {
        hamburger.addEventListener('click', () => {
            hamburger.classList.toggle('active');
            navLinksContainer.classList.toggle('active');
        });

        navLinksItems.forEach(link => {
            link.addEventListener('click', () => {
                hamburger.classList.remove('active');
                navLinksContainer.classList.remove('active');
            });
        });
    }

    // 2. Mobile Footer Accordion
    const footerHeaders = document.querySelectorAll('.footer-col h3');
    footerHeaders.forEach(header => {
        header.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                const parentCol = header.parentElement;
                const icon = header.querySelector('.toggle-icon');
                parentCol.classList.toggle('active');
                if (icon) {
                    icon.textContent = parentCol.classList.contains('active') ? '-' : '+';
                }
            }
        });
    });

    // 3. Scrollspy & Smooth Scrolling for Home link
    const sections = document.querySelectorAll("section, footer"); 
    const navLinks = document.querySelectorAll(".nav-links a");

    const homeLink = document.querySelector('.nav-links a[href="#home"]');
    if (homeLink) {
        homeLink.addEventListener('click', (e) => {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: "smooth" });
        });
    }

    window.addEventListener("scroll", () => {
        let current = "";
        sections.forEach((section) => {
            const sectionTop = section.offsetTop;
            // Adjustment factor slightly increased to match the newly added padding top
            if (window.scrollY >= sectionTop - 150) {
                current = section.getAttribute("id");
            }
        });
        if (window.scrollY < 100) current = "home";
        if ((window.innerHeight + Math.round(window.scrollY)) >= document.body.offsetHeight - 50) current = "contact";

        navLinks.forEach((link) => {
            link.classList.remove("active");
            const href = link.getAttribute("href");
            if (href && href.includes("#")) {
                const targetId = href.replace("#", "");
                if (targetId === current) link.classList.add("active");
            }
        });
    });

    // 4. Hero Slider Initialization
    const slides = document.getElementsByClassName("slide");
    if (slides.length > 0) {
        showSlides(slideIndex);
        startSliderTimer();

        const heroSection = document.querySelector('.hero-slider');
        if (heroSection) {
            heroSection.addEventListener('mouseenter', () => isHeroHovered = true);
            heroSection.addEventListener('mouseleave', () => { 
                isHeroHovered = false; 
                resetSliderTimer(); 
            });
        }
    }

    // 5. 3D Services Carousel Initialization
    const carouselItems = document.querySelectorAll('.carousel-item');
    if (carouselItems.length > 0) {
        requestAnimationFrame(renderCarousel);
        
        const carouselContainer = document.getElementById('servicesCarousel');
        if (carouselContainer) {
            carouselContainer.addEventListener('mouseenter', () => isHovered = true);
            carouselContainer.addEventListener('mouseleave', () => isHovered = false);
        }
    }

    // 6. About Us Highlights Automatic Slideshow
    const highlightSlides = document.querySelectorAll(".highlight-slide");
    if (highlightSlides.length >= 2) {
        let currentHighlight = 0;
        setInterval(() => {
            const next = (currentHighlight + 1) % highlightSlides.length;
            const oldSlide = highlightSlides[currentHighlight];
            const newSlide = highlightSlides[next];

            newSlide.style.transition = "none";
            newSlide.classList.remove("prev");
            void newSlide.offsetWidth;
            newSlide.style.transition = "";

            oldSlide.classList.remove("active");
            oldSlide.classList.add("prev");
            newSlide.classList.add("active");

            currentHighlight = next;
        }, 3000);
    }

});

/* =========================
   Global Scroll Reveal
========================= */
function reveal() {
    var reveals = document.querySelectorAll(".reveal");
    for (var i = 0; i < reveals.length; i++) {
        var windowHeight = window.innerHeight;
        var elementTop = reveals[i].getBoundingClientRect().top;
        var elementVisible = 100;
        if (elementTop < windowHeight - elementVisible) {
            reveals[i].classList.add("active");
        }
    }
}
window.addEventListener("scroll", reveal);
reveal();

/* =========================
   Hero Slider Functions
========================= */
let slideIndex = 1;
let slideInterval;
let isHeroHovered = false; 

function changeSlide(n) { showSlides(slideIndex += n); resetSliderTimer(); }
function currentSlide(n) { showSlides(slideIndex = n); resetSliderTimer(); }

function showSlides(n) {
    let i;
    let slides = document.getElementsByClassName("slide");
    let dots = document.getElementsByClassName("dot");
    let prevBtn = document.querySelector(".prev");
    let nextBtn = document.querySelector(".next");
    
    if (slides.length === 0) return;

    if (n > slides.length) { slideIndex = 1; }
    if (n < 1) { slideIndex = slides.length; }
    
    for (i = 0; i < slides.length; i++) slides[i].classList.remove("active");
    for (i = 0; i < dots.length; i++) dots[i].classList.remove("active");
    
    slides[slideIndex - 1].classList.add("active");
    if (dots.length > 0) dots[slideIndex - 1].classList.add("active");

    const activeRevealElements = slides[slideIndex - 1].querySelectorAll('.reveal');
    activeRevealElements.forEach(el => {
        el.classList.remove('active');
        setTimeout(() => el.classList.add('active'), 100);
    });

    if (prevBtn && nextBtn) {
        prevBtn.style.display = (slideIndex === 1) ? "none" : "flex";
        nextBtn.style.display = (slideIndex === slides.length) ? "none" : "flex";
    }
}

function startSliderTimer() {
    clearInterval(slideInterval);
    slideInterval = setInterval(function() {
        if (!isHeroHovered) showSlides(slideIndex += 1);
    }, 5000); 
}

function resetSliderTimer() { 
    clearInterval(slideInterval); 
    startSliderTimer(); 
}

/* ========================================================
   Services True Infinite 3D Carousel (Absolute Math Engine)
======================================================== */
let absoluteFloatIndex = 0;   
let targetAbsoluteIndex = null; 
let isHovered = false;        
let isModalOpen = false;  
let isPausedByClick = false; 
let clickPauseTimeout;       
const autoSpeed = 0.004;    

function renderCarousel() {
    const carouselItems = document.querySelectorAll('.carousel-item');
    const totalItems = carouselItems.length;
    if (totalItems === 0) return;

    if (targetAbsoluteIndex !== null) {
        absoluteFloatIndex += (targetAbsoluteIndex - absoluteFloatIndex) * 0.08;
        if (Math.abs(targetAbsoluteIndex - absoluteFloatIndex) < 0.005) {
            absoluteFloatIndex = targetAbsoluteIndex;
            targetAbsoluteIndex = null; 
        }
    } else if (!isHovered && !isModalOpen && !isPausedByClick) {
        absoluteFloatIndex += autoSpeed;
    }

    let normalizedFloat = ((absoluteFloatIndex % totalItems) + totalItems) % totalItems;

    carouselItems.forEach((item, index) => {
        let diff = index - normalizedFloat;
        if (diff > totalItems / 2) diff -= totalItems;
        if (diff < -totalItems / 2) diff += totalItems;
        
        let absDiff = Math.abs(diff);
        let translateX = diff * 200;            
        let translateZ = -absDiff * 250;        
        let rotateY = -diff * 12;              
        let scale = Math.max(0.6, 1 - absDiff * 0.1); 
        let opacity = Math.max(0, 1 - absDiff * 0.35);
        let zIndex = Math.round(100 - absDiff * 10);

        item.style.transform = `translateX(${translateX}px) translateZ(${translateZ}px) rotateY(${rotateY}deg) scale(${scale})`;
        item.style.opacity = opacity;
        item.style.zIndex = zIndex;
        
        if (absDiff < 0.3) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
    requestAnimationFrame(renderCarousel);
}

function pauseCarouselTemporarily() {
    isPausedByClick = true;
    clearTimeout(clickPauseTimeout); 
    clickPauseTimeout = setTimeout(() => { isPausedByClick = false; }, 5000);
}

function navigateCarousel(direction) {
    const carouselItems = document.querySelectorAll('.carousel-item');
    const totalItems = carouselItems.length;
    if (totalItems === 0) return;

    let currentCenter = Math.round(absoluteFloatIndex);
    if (targetAbsoluteIndex !== null) currentCenter = targetAbsoluteIndex;
    targetAbsoluteIndex = currentCenter + direction;
    pauseCarouselTemporarily();
}

function handleCarouselClick(clickedIndex, title, desc) {
    const carouselItems = document.querySelectorAll('.carousel-item');
    const totalItems = carouselItems.length;
    if (totalItems === 0) return;

    let currentCenter = Math.round(absoluteFloatIndex);
    if (targetAbsoluteIndex !== null) currentCenter = targetAbsoluteIndex;
    let currentNormalized = ((currentCenter % totalItems) + totalItems) % totalItems;
    let diff = clickedIndex - currentNormalized;
    
    if (diff > totalItems / 2) diff -= totalItems;
    if (diff < -totalItems / 2) diff += totalItems;

    if (diff === 0) {
        openModal(title, desc);
    } else {
        targetAbsoluteIndex = currentCenter + diff;
        pauseCarouselTemporarily();
    }
}

/* =========================
   Modal Functionality
========================= */
function openModal(title, description) {
    const modal = document.getElementById("serviceModal");
    const modalTitle = document.getElementById("modalTitle");
    const modalDesc = document.getElementById("modalDesc");

    if (modal && modalTitle && modalDesc) {
        modalTitle.innerText = title;
        modalDesc.innerText = description;
        modal.style.display = "block";
        isModalOpen = true; 
    }
}

function closeModal() {
    const modal = document.getElementById("serviceModal");
    if (modal) {
        modal.style.display = "none";
        isModalOpen = false; 
    }
}

window.onclick = function(event) {
    const modal = document.getElementById("serviceModal");
    if (event.target == modal) {
        modal.style.display = "none";
        isModalOpen = false;
    }
}

/* =========================
   Back to Top Functionality
========================= */
window.addEventListener("scroll", () => {
    const backToTopBtn = document.getElementById("backToTopBtn");
    if (backToTopBtn) {
        if (window.scrollY > 300) {
            backToTopBtn.classList.add("show");
        } else {
            backToTopBtn.classList.remove("show");
        }
    }
});

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: "smooth" });
}