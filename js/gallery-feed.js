document.addEventListener('DOMContentLoaded', function() {
  console.log('Gallery feed script loaded');

  function updateGalleryContainer(container) {
    const instance = container.dataset.instance;
    const endpoint = container.dataset.endpoint;
    const grid = container.querySelector('.gallery-grid');

    if (!instance || !endpoint || !grid) {
      console.warn('Missing data-instance, data-endpoint, or .gallery-grid');
      return;
    }

    grid.classList.add('loading');
    grid.innerHTML = '<div style="padding:40px;text-align:center;color:#64748b;grid-column:1/-1;">Loading gallery...</div>';

    fetch(endpoint)
      .then(res => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
      })
      .then(galleryItems => {
        if (!Array.isArray(galleryItems) || galleryItems.length === 0) {
          grid.innerHTML = '<div style="padding:60px;text-align:center;color:#64748b;grid-column:1/-1;">No gallery images found.</div>';
          grid.classList.remove('loading');
          return;
        }

        let html = '';
        galleryItems.forEach((item, index) => {
          const title = item.title?.rendered ? item.title.rendered.replace(/<[^>]*>/g, '').trim() : `Image ${index + 1}`;
          const link = item.link || '#';
          let imageUrl = '';
          if (item._embedded?.['wp:featuredmedia']?.[0]) {
            const media = item._embedded['wp:featuredmedia'][0];
            // Prefer large size for gallery
            imageUrl = media.media_details?.sizes?.large?.source_url || media.source_url || '';
          } else if (item.featured_media && item._embedded?.['wp:featuredmedia']) {
            imageUrl = item._embedded['wp:featuredmedia'][0]?.source_url || '';
          }

          if (!imageUrl) return; // Skip items without images

          html += `
            <figure class="gallery-item" style="margin:0;background:none;box-shadow:0 4px 16px rgba(0,0,0,0.1);border-radius:12px;overflow:hidden;transition:all 0.3s ease;">
              <a href="${link}" style="display:block;">
                <img src="${imageUrl}" alt="${title.replace(/"/g, '"')}" 
                     style="width:100%;height:250px;object-fit:cover;display:block;cursor:pointer;"
                     loading="${index < 6 ? 'eager' : 'lazy'}">
              </a>
              <figcaption style="padding:16px 20px;font-size:14px;color:#374151;">
                <a href="${link}" style="color:#163447;text-decoration:none;font-weight:600;font-size:15px;line-height:1.4;">${title}</a>
              </figcaption>
            </figure>
          `;
        });

        grid.innerHTML = html || '<div style="padding:60px;text-align:center;color:#64748b;grid-column:1/-1;">No images available in gallery.</div>';
        grid.classList.remove('loading');

        // Masonry layout simulation with CSS Grid
        setTimeout(() => {
          grid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(320px, 1fr))';
          grid.style.gap = '24px';
        }, 100);

        console.log(`Gallery updated for instance ${instance}: ${galleryItems.length} images`);
      })
      .catch(error => {
        console.error(`Gallery fetch error:`, error);
        grid.innerHTML = '<div style="padding:60px;text-align:center;color:#dc2626;">Unable to load gallery. Please refresh.</div>';
        grid.classList.remove('loading');
      });
  }

  function initGalleryFeeds() {
    const containers = document.querySelectorAll('.gallery-container[data-instance]');
    containers.forEach(updateGalleryContainer);
  }

  initGalleryFeeds();
  
  // Refresh every 2 minutes (galleries change less frequently)
  setInterval(initGalleryFeeds, 120000);
  
  // Lightbox support (basic)
  document.addEventListener('click', function(e) {
    if (e.target.closest('.gallery-item img')) {
      e.preventDefault();
      const link = e.target.closest('a').href;
      if (link && link !== '#') {
        window.open(link, '_blank');
      }
    }
  });
});

