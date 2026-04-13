document.addEventListener('DOMContentLoaded', function() {
  console.log('Live time script loaded');

  function initLiveTimes() {
    const timeCards = document.querySelectorAll('[data-live-time]');
    console.log(`Found ${timeCards.length} live time cards`);

    timeCards.forEach(card => {
      const instance = card.dataset.instance;
      const timeZone = card.dataset.timezone;
      const is12h = card.dataset.format.toLowerCase() === '12h';
      const timeEl = document.getElementById(`live-time-${instance}`);
      const dateEl = document.getElementById(`live-date-${instance}`);

      if (!timeEl || !dateEl) {
        console.warn(`Time elements missing for instance ${instance}`);
        return;
      }

      function updateClock() {
        try {
          const now = new Date();

          const timeOpts = {
            timeZone,
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit',
            hour12: is12h
          };

          const dateOpts = {
            timeZone,
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
          };

          timeEl.textContent = now.toLocaleTimeString('en-US', timeOpts);
          dateEl.textContent = now.toLocaleDateString('en-US', dateOpts);
        } catch (e) {
          console.error(`Clock error for ${instance}:`, e);
          timeEl.textContent = '--:--:--';
        }
      }

      updateClock();
      setInterval(updateClock, 1000);
      console.log(`Live time initialized for ${instance} (${timeZone})`);
    });
  }

  initLiveTimes();
});
