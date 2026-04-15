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

html += `\n            <article style="padding:16px 0;border-bottom:1px solid #e5e7eb;">\n              ${featuredImage ? `\n                <a href="${link}">\n                  <img src="${featuredImage}" alt="${title.replace(/"/g, '"')}" style="width:100%; height:200px; object-fit:cover; border-radius:8px; display:block; margin-bottom:12px;">\n                </a>` : ''}\n              \n              <div style="font-size:12px;color:#6b7280;margin-bottom:4px;">📅 ${date}</div>\n              \n              <h3 style="margin:0 0 8px 0;font-size:18px;line-height:1.4;">\n                <a href="${link}" style="text-decoration:none;color:#163447;font-weight:600;">${title}</a>\n              </h3>\n            </article>\n          `;
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

  // Trigger-based updates with IntersectionObserver (replaces setInterval)
  const intervalMap = new Map();
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      const container = entry.target;
      const instance = container.dataset.instance;
      if (!instance) return;

      if (entry.isIntersecting) {
        if (!intervalMap.has(instance)) {
          console.log(`News container ${instance} visible - starting refresh`);
          // First update
          updateNewsContainer(container);
          // Interval while visible
          const intId = setInterval(() => updateNewsContainer(container), 30000);
          intervalMap.set(instance, intId);
        }
      } else if (intervalMap.has(instance)) {
        console.log(`News container ${instance} hidden - stopping refresh`);
        clearInterval(intervalMap.get(instance));
        intervalMap.delete(instance);
      }
    });
  }, { threshold: 0.1 });

  document.querySelectorAll('.news-container[data-instance]').forEach(obs => observer.observe(obs));
});

