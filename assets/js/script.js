
// Carousel logic (same as before, with smooth auto)
(function() {
    const track = document.getElementById('carouselTrack');
    if (!track || track.children.length === 0) return;
    let slides = Array.from(track.children);
    let currentIndex = 0;
    let autoInterval;
    const prev = document.getElementById('prevBtn');
    const next = document.getElementById('nextBtn');
    const indicators = document.getElementById('carouselIndicators');

    function createIndicators() {
        indicators.innerHTML = '';
        slides.forEach((_, i) => {
            const dot = document.createElement('button');
            dot.className = 'w-2 h-2 rounded-full bg-white/50 transition-all duration-200';
            if (i === currentIndex) dot.classList.add('!w-6', '!bg-green-400');
            dot.addEventListener('click', () => goToSlide(i));
            indicators.appendChild(dot);
        });
    }

    function updateIndicators() {
        const dots = indicators.children;
        for (let i = 0; i < dots.length; i++) {
            if (i === currentIndex) {
                dots[i].classList.add('!w-6', '!bg-green-400');
                dots[i].classList.remove('bg-white/50');
            } else {
                dots[i].classList.remove('!w-6', '!bg-green-400');
                dots[i].classList.add('bg-white/50');
            }
        }
    }

    function goToSlide(index) {
        if (index < 0) index = slides.length - 1;
        if (index >= slides.length) index = 0;
        currentIndex = index;
        track.style.transform = `translateX(-${currentIndex * 100}%)`;
        updateIndicators();
        resetAuto();
    }

    function nextSlide() { goToSlide(currentIndex + 1); }
    function prevSlide() { goToSlide(currentIndex - 1); }
    function startAuto() { autoInterval = setInterval(nextSlide, 6000); }
    function resetAuto() { clearInterval(autoInterval); startAuto(); }

    if (prev) prev.addEventListener('click', prevSlide);
    if (next) next.addEventListener('click', nextSlide);
    createIndicators();
    startAuto();
    const container = document.querySelector('.carousel-container');
    if (container) {
        container.addEventListener('mouseenter', () => clearInterval(autoInterval));
        container.addEventListener('mouseleave', startAuto);
    }
})();

// Filter category button logic
const catBtns = document.querySelectorAll('.category-btn');
const tagItems = document.querySelectorAll('.tag-item');
function setCategory(category, activeBtn) {
    catBtns.forEach(btn => {
        btn.classList.remove('active', 'border-green-500', 'bg-green-500/20', 'text-green-400');
        btn.classList.add('border-slate-600', 'bg-slate-800/40', 'text-slate-300');
    });
    if (activeBtn) {
        activeBtn.classList.add('active', 'border-green-500', 'bg-green-500/20', 'text-green-400');
    }
    tagItems.forEach(tag => {
        if (category === 'all') tag.style.display = 'flex';
        else if (category === 'none') tag.style.display = 'none';
        else tag.style.display = tag.classList.contains(category) ? 'flex' : 'none';
    });
}
catBtns.forEach(btn => {
    btn.addEventListener('click', function() {
        setCategory(this.getAttribute('data-category'), this);
    });
});
const defaultCat = document.querySelector('.category-btn.active');
if (defaultCat) setCategory(defaultCat.getAttribute('data-category'), defaultCat);
else setCategory('none', null);
