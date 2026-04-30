(() => {
  const topButton = document.querySelector('[data-plan-back-to-top]');
  const topTarget = document.getElementById('plan-your-day-settings-top');
  const topHeading = document.getElementById('plan-your-day-settings-title');

  if (!(topButton instanceof HTMLButtonElement) || !(topTarget instanceof HTMLElement)) {
    return;
  }

  const toggleVisibility = () => {
    const isVisible = window.scrollY > 280;

    topButton.hidden = !isVisible;
    topButton.classList.toggle('is-visible', isVisible);
  };

  topButton.addEventListener('click', () => {
    topTarget.scrollIntoView({
      behavior: 'smooth',
      block: 'start',
    });

    if (topHeading instanceof HTMLElement) {
      window.setTimeout(() => {
        topHeading.focus({
          preventScroll: true,
        });
      }, 180);
    }
  });

  window.addEventListener(
    'scroll',
    () => {
      toggleVisibility();
    },
    {
      passive: true,
    }
  );

  toggleVisibility();
})();
