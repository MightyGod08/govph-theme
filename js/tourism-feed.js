document.addEventListener('DOMContentLoaded', function() {
  console.log('Tourism feed script loaded');

  function updateTourismContainer(container) {
    const instance = container.dataset.instance;
    const endpoint = container.dataset.endpoint;
    const list = container.querySelector('.tourism-list');

    if (!instance || !endpoint || !list) {
      console.warn('Missing data-instance, data-endpoint, or .tourism-list');
      return;
    }

    list.classList.add('loading');
    list.innerHTML = '<div style="padding:24px;text-align:center;color:#64748b;">Discovering attractions...</div>';

    fetch(endpoint)
      .then(res => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
      })
      .then(items => {
        if (!Array.isArray(items) || items.length === 0) {
          list.innerHTML = '<div style="padding:24px;text-align:center;color:#64748b;">No tourism attractions found.</div>';
          list.classList.remove('loading');
          return;
        }

        let html = '<div style="display:grid;gap:24px;">';
        items.forEach(item => {
          const title = item.title?.rendered ? item.title.rendered.replace(/<[^>]*>/g, '').trim() : 'Attraction';
          const excerpt = item.excerpt?.rendered ? item.excerpt.rendered.replace(/<[^>]*>/g, '').trim() : '';
          const link = item.link || '#';
          let image = '';
          if (item._embedded?.['wp:featuredmedia']?.[0]) {
            const img = item._embedded['wp:featuredmedia'][0];
            image = img.media_details?.sizes?.large?.source_url || img.source_url || '';
          }
          const location = item.meta?.tourism_location || 'Camaligan';

          html += `
            <article style="border-radius:16px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,0.12);transition:transform 0.3s ease;background:#ffffff;">
              ${image ? `<img src="${image}" alt="${title.replace(/"/g, '"')}" style="width:100%;height:280px;object-fit:cover;display:block;">` : ''}
              <div style="padding:28px;">
                <div style="display:flex;align-items:center;gap:8px;color:#10b981;font-weight:600;font-size:14px;margin-bottom:12px;">
                  <span style="font-size:18px;">📍</span>
                  ${location}
                </div>
                <h3 style="margin:0 0 16px;font-size:24px;line-height:1.3;color:#163447;font-weight:700;">${title}</h3>
                <div style="color:#555;line-height:1.7;margin-bottom:24px;font-size:16px;">${excerpt.split(' ').slice(0,35).join(' ') || ''}...</div>
                <a href="${link}" style="display:inline-flex;align-items:center;gap:8px;padding:14px 24px;background:linear-gradient(135deg,#10b981,#059669);color:#ffffff;text-decoration:none;border-radius:12px;font-weight:600;font-size:16px;transition:all 0.3s ease;">
                  Explore Attraction 
                  <span style="font-size:20px;">→</span>
                </a>
              </div>
            </article>
          `;
        });
        html += '</div>';

        list.innerHTML = html;
        list.classList.remove('loading');
        console.log(`Tourism updated for instance ${instance}: ${items.length} attractions`);
      })
      .catch(error => {
        console.error(`Tourism fetch error:`, error);
        list.innerHTML = '<div style="padding:24px;text-align:center;color:#9b1c1c;">Unable to load tourism info. Please refresh.</div>';
        list.classList.remove('loading');
      });
  }

  function initTourismFeeds() {
    const containers = document.querySelectorAll('.tourism-container[data-instance]');
    containers.forEach(updateTourismContainer);
  }

  initTourismFeeds();
  setInterval(initTourismFeeds, 60000); // Refresh every minute
});

