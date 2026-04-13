document.addEventListener('DOMContentLoaded', function() {
  console.log('News feed script loaded');

  function updateNewsContainer(container) {
    const instance = container.dataset.instance;
    const endpoint = container.dataset.endpoint;
    const list = container.querySelector('.news-list');

    if (!instance || !endpoint || !list) {
      console.warn('Missing data-instance, data-endpoint, or .news-list');
      return;
    }

    // Add loading state
    list.classList.add('loading');
    list.innerHTML = '<div style="padding:24px;text-align:center;color:#64748b;font-style:italic;">Updating news...</div>';

    fetch(endpoint)
      .then(res => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
      })
      .then(newsItems => {
        if (!Array.isArray(newsItems) || newsItems.length === 0) {
          list.innerHTML = '<div style="padding:24px;text-align:center;color:#64748b;font-style:italic;">No news items found.</div>';
          list.classList.remove('loading');
          return;
        }

        let html = '';
        newsItems.forEach(item => {
          const title = item.title?.rendered ? item.title.rendered.replace(/<[^>]*>/g, '').trim() || 'Untitled' : 'Untitled';
          const excerpt = item.excerpt?.rendered ? item.excerpt.rendered.replace(/<[^>]*>/g, '').trim() : '';
          const date = item.date ? new Date(item.date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : '';
          const link = item.link || '#';
          let featuredImage = '';
          if (item._embedded?.['wp:featuredmedia']?.[0]) {
            const img = item._embedded['wp:featuredmedia'][0];
            featuredImage = img.media_details?.sizes?.medium?.source_url || img.source_url || '';
          }

          html += `
            <article style="border-bottom:1px solid #d1d8df;padding:20px;background:transparent; box0-shadow:0 1px 3px rgba(0,0,0,0.05);border-radius:8px; font-family:system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
              <div style="display:flex;flex-direction:column;gap:8px;">
                <h3 style="margin:0;font-size:20px;line-height:1.4;color:#163447;">
                  <a href="${link}" style="text-decoration:none;color:#163447;font-weight:600;">${title}</a>
                </h3>
                <div style="display:flex;gap:12px;align-items:center;color:#5b6b79;font-size:14px;">
                  <span style="font-weight:600;">📅 ${date}</span>
                  ${featuredImage ? `<img src="${featuredImage}" alt="${title.replace(/"/g, '"')}" style="width:60px;height:40px;object-fit:cover;border-radius:4px;">` : ''}
                </div>
                <div style="color:#555;line-height:1.6;font-size:15px;">${excerpt.split(' ').slice(0,25).join(' ') || ''}...</div>
                <a href="${link}" style="color:#0b3440;text-decoration:none;font-weight:600;font-size:14px;">Read More →</a>
              </div>
            </article>
          `;
        });

        list.innerHTML = html;
        list.classList.remove('loading');
        console.log(`News updated for instance ${instance}: ${newsItems.length} items`);
      })
      .catch(error => {
        console.error(`News fetch error for ${instance}:`, error);
        list.innerHTML = '<div style="padding:24px;text-align:center;color:#9b1c1c;font-weight:500;">Unable to load news right now. Please refresh the page.</div>';
        list.classList.remove('loading');
      });
  }

  function initNewsFeeds() {
    const containers = document.querySelectorAll('.news-container[data-instance]');
    console.log(`Found ${containers.length} news containers`);
    containers.forEach(updateNewsContainer);
  }

  // Initial load
  initNewsFeeds();

  // Auto-update every 30 seconds
  setInterval(initNewsFeeds, 30000);
});
