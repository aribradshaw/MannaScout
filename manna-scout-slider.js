(function () {
  'use strict';

  function initSlider(slider) {
    const track = slider.querySelector('.ms-slider-track');
    const wrapper = slider.querySelector('.ms-slider-wrapper');
    const originalCards = Array.from(slider.querySelectorAll('.ms-slider-card'));
    const leftArrow = slider.querySelector('.ms-slider-arrow-left');
    const rightArrow = slider.querySelector('.ms-slider-arrow-right');
    const details = Array.from(slider.querySelectorAll('.ms-slider-detail'));
    
    if (!track || !wrapper || originalCards.length === 0) return;

    // Mobile detection
    const isMobile = window.matchMedia('(max-width: 768px)').matches;
    const cardsPerView = isMobile ? 1 : 3;
    const clonesNeeded = isMobile ? 1 : 2;

    // Clone cards for seamless loop
    if (originalCards.length > 1) {
      // Clone last cards and prepend
      for (let i = 0; i < clonesNeeded; i++) {
        const clone = originalCards[originalCards.length - 1 - i].cloneNode(true);
        clone.classList.add('ms-clone');
        wrapper.insertBefore(clone, wrapper.firstChild);
      }
      
      // Clone first cards and append
      for (let i = 0; i < clonesNeeded; i++) {
        const clone = originalCards[i].cloneNode(true);
        clone.classList.add('ms-clone');
        wrapper.appendChild(clone);
      }
    }

    // Get all cards including clones
    const cards = Array.from(wrapper.querySelectorAll('.ms-slider-card'));
    const realCardCount = originalCards.length;
    
    // Start at the first real card (after prepended clones)
    let currentIndex = clonesNeeded;
    let isTransitioning = false;
    let touchStartX = 0;
    let touchEndX = 0;
    let isDragging = false;

    function updateSlider(instant = false) {
      if (isTransitioning && !instant) return;
      isTransitioning = true;

      const cardWidth = cards[0].offsetWidth;
      const gap = parseInt(getComputedStyle(wrapper).gap) || 16;
      const translateX = -(currentIndex * (cardWidth + gap));
      
      if (instant) {
        wrapper.style.transition = 'none';
      } else {
        wrapper.style.transition = 'transform 0.4s ease-in-out';
      }
      
      wrapper.style.transform = `translateX(${translateX}px)`;

      // Calculate real index for details (0 to realCardCount - 1)
      let realIndex = currentIndex - clonesNeeded;
      if (realIndex < 0) realIndex = realCardCount + realIndex;
      if (realIndex >= realCardCount) realIndex = realIndex % realCardCount;

      // Update active detail
      details.forEach((detail, idx) => {
        detail.classList.toggle('active', idx === realIndex);
      });

      // Update card active state (only for real cards, not clones)
      cards.forEach((card, idx) => {
        const isRealCard = idx >= clonesNeeded && idx < clonesNeeded + realCardCount;
        const cardRealIndex = idx - clonesNeeded;
        card.classList.toggle('active', isRealCard && cardRealIndex === realIndex);
      });

      // Handle seamless loop - jump to real cards when at clones
      if (!instant) {
        setTimeout(() => {
          if (currentIndex < clonesNeeded) {
            // At prepended clones, jump to end
            currentIndex = clonesNeeded + realCardCount - (clonesNeeded - currentIndex);
            updateSlider(true);
          } else if (currentIndex >= clonesNeeded + realCardCount) {
            // At appended clones, jump to beginning
            currentIndex = clonesNeeded + (currentIndex - (clonesNeeded + realCardCount));
            updateSlider(true);
          }
          isTransitioning = false;
        }, 400);
      } else {
        setTimeout(() => {
          isTransitioning = false;
        }, 50);
      }
    }

    function goToNext() {
      if (isTransitioning) return;
      currentIndex++;
      updateSlider();
    }

    function goToPrev() {
      if (isTransitioning) return;
      currentIndex--;
      updateSlider();
    }

    // Arrow navigation
    if (leftArrow) {
      leftArrow.addEventListener('click', goToPrev);
    }
    if (rightArrow) {
      rightArrow.addEventListener('click', goToNext);
    }

    // Card click to show details
    cards.forEach((card, idx) => {
      card.addEventListener('click', function () {
        // Only handle clicks on real cards, not clones
        if (idx >= clonesNeeded && idx < clonesNeeded + realCardCount) {
          currentIndex = idx;
          updateSlider();
        }
      });
    });

    // Touch/swipe support
    track.addEventListener('touchstart', function (e) {
      touchStartX = e.touches[0].clientX;
      isDragging = true;
      wrapper.style.transition = 'none';
    }, { passive: true });

    track.addEventListener('touchmove', function (e) {
      if (!isDragging) return;
      touchEndX = e.touches[0].clientX;
      const diff = touchStartX - touchEndX;
      const cardWidth = cards[0].offsetWidth;
      const gap = parseInt(getComputedStyle(wrapper).gap) || 16;
      const baseTranslate = -(currentIndex * (cardWidth + gap));
      const dragTranslate = baseTranslate - diff;
      wrapper.style.transform = `translateX(${dragTranslate}px)`;
    }, { passive: true });

    track.addEventListener('touchend', function () {
      if (!isDragging) return;
      isDragging = false;
      wrapper.style.transition = 'transform 0.4s ease-in-out';

      const diff = touchStartX - touchEndX;
      const threshold = 50;

      if (Math.abs(diff) > threshold) {
        if (diff > 0) {
          goToNext();
        } else {
          goToPrev();
        }
      } else {
        updateSlider();
      }
    }, { passive: true });

    // Keyboard navigation
    slider.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') {
        e.preventDefault();
        goToPrev();
      } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        goToNext();
      }
    });

    // Initialize - set initial position without transition
    updateSlider(true);

    // Handle window resize
    let resizeTimeout;
    window.addEventListener('resize', function () {
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(function () {
        updateSlider();
      }, 250);
    });
  }

  // Initialize all sliders on page load
  function initAllSliders() {
    document.querySelectorAll('.manna-scout-slider').forEach(initSlider);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAllSliders);
  } else {
    initAllSliders();
  }

  // Re-initialize on dynamic content (for AJAX-loaded content)
  if (typeof MutationObserver !== 'undefined') {
    const observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        mutation.addedNodes.forEach(function (node) {
          if (node.nodeType === 1) {
            if (node.classList && node.classList.contains('manna-scout-slider')) {
              initSlider(node);
            } else {
              const sliders = node.querySelectorAll && node.querySelectorAll('.manna-scout-slider');
              if (sliders) {
                sliders.forEach(initSlider);
              }
            }
          }
        });
      });
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }
})();

