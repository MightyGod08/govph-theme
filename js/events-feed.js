document.addEventListener('DOMContentLoaded', function() {
  console.log('Events feed script loaded');

  function updateEventsContainer(container) {
    const instance = container.dataset.instance;
    const endpoint = container.dataset.endpoint;
    const list = container.querySelector('.events-list');

    if (!instance || !endpoint || !list) {
      console.warn('Missing data-instance, data-endpoint, or .events-list');
      return;
    }

    // Add loading state
    list.classList.add('loading');
    list.innerHTML = '<div style="padding:24px;text-align:center;color:#64748b;font-style:italic;">Updating events...</div>';

    fetch(endpoint)
      .then(res => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
      })
      .then(events => {
        if (!Array.isArray(events) || events.length === 0) {
          list.innerHTML = '<div style="padding:24px;text-align:center;color:#64748b;font-style:italic;">No events found.</div>';
          list.classList.remove('loading');
          return;
        }

        // Featured event (first)
        const featured = events[0];
        const featuredTitle = featured.title?.rendered ? featured.title.rendered.replace(/<[^>]*>/g, '').trim() || 'Featured Event' : 'Featured Event';
        const featuredDate = featured.date ? new Date(featured.date).toLocaleDateString('en-US', { 
          weekday: 'long', 
          year: 'numeric', 
          month: 'long', 
          day: 'numeric',
          hour: 'numeric',
          minute: '2-digit'
        }) : '';
        const featuredLink = featured.link || '#';
        let featuredImage = '';
        if (featured._embedded?.['wp:featuredmedia']?.[0]) {
          const img = featured._embedded['wp:featuredmedia'][0];
          featuredImage = img.media_details?.sizes?.large?.source_url || img.source_url || '';
        }
        const featuredExcerpt = featured.excerpt?.rendered ? featured.excerpt.rendered.replace(/<[^>]*>/g, '').trim() : '';

        let html = `
          <div style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,0.12);margin-bottom:24px;">
            ${featuredImage ? `<img src="${featuredImage}" alt="${featuredTitle.replace(/"/g, '"')}" style="width:100%;height:300px;object-fit:cover;display:block;">` : ''}
            <div style="padding:32px;">
              <h2 style="margin:0 0 16px;font-size:32px;line-height:1.2;color:#163447;font-weight:700;">${featuredTitle}</h2>
              <div style="color:#5b6b79;font-size:16px;margin-bottom:16px;font-weight:500;">
                📅 <strong>${featuredDate}</strong>
              </div>
              <div style="color:#555;line-height:1.6;margin-bottom:24px;font-size:16px;">${featuredExcerpt.split(' ').slice(0,40).join(' ') || ''}...</div>
              <a href="${featuredLink}" style="display:inline-block;padding:16px 24px;background:#0b3440;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;font-size:16px;">View Event Details</a>
            </div>
          </div>
        `;

        // Gallery for remaining events (grid)
        html += '<div class="events-gallery" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;">';
        events.slice(1).forEach(event => {
          const title = event.title?.rendered ? event.title.rendered.replace(/<[^>]*>/g, '').trim() : 'Event';
          const date = event.date ? new Date(event.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : '';
          const link = event.link || '#';
          let image = '';
          if (event._embedded?.['wp:featuredmedia']?.[0]) {
            const img = event._embedded['wp:featuredmedia'][0];
            image = img.media_details?.sizes?.thumbnail?.source_url || img.source_url || '';
          }

          html += `
            <article style="background:#f9fbfd;border-radius:12px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.08);transition:transform 0.2s ease;">
              ${image ? `<img src="${image}" alt="${title.replace(/"/g, '"')}" style="width:100%;height:180px;object-fit:cover;display:block;">` : ''}
              <div style="padding:20px;">
                <div style="color:#5b6b79;font-size:14px;margin-bottom:8px;">📅 ${date}</div>
                <h3 style="margin:0 0 12px;font-size:18px;line-height:1.4;color:#163447;font-weight:600;">${title}</h3>
                <a href="${link}" style="color:#0b3440;text-decoration:none;font-weight:600;font-size:14px;">View Details →</a>
              </div>
            </article>
          `;
        });
        html += '</div>';

        list.innerHTML = html;
        list.classList.remove('loading');
        console.log(`Events updated for instance ${instance}: ${events.length} items`);
      })
      .catch(error => {
        console.error(`Events fetch error for ${instance}:`, error);
        list.innerHTML = '<div style="padding:24px;text-align:center;color:#9b1c1c;font-weight:500;">Unable to load events right now. Please refresh.</div>';
        list.classList.remove('loading');
      });
  }

  function initEventsFeeds() {
    const containers = document.querySelectorAll('.events-container[data-instance]');
    console.log(`Found ${containers.length} events containers`);
    containers.forEach(updateEventsContainer);
  }

  // Initial load
  initEventsFeeds();

  // Auto-update every 5 minutes
  setInterval(initEventsFeeds, 300000);
});

