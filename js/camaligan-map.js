document.addEventListener('DOMContentLoaded', function () {
  const wrapper = document.querySelector('.cim-wrapper');
  if (!wrapper) return;

  const svg = wrapper.querySelector('.cim-map-container svg');
const mapStage = wrapper;   const mapViewport = document.getElementById('cim-map-viewport') || wrapper;   const mapSurface = document.getElementById('cim-map-surface') || svg;   const infoPanel = document.getElementById('cim-info-panel');   const select = document.getElementById('cim-barangay-select');   const tooltip = document.getElementById('cim-map-tooltip') || createTooltip();   const zoomInButton = document.getElementById('cim-zoom-in');   const zoomOutButton = document.getElementById('cim-zoom-out');   const fitButton = document.getElementById('cim-fit-map');   if (!svg) return;   function createTooltip() {     const tt = document.createElement('div');     tt.id = 'cim-map-tooltip';     tt.className = 'cim-map-tooltip';     tt.style.cssText = 'position:absolute;z-index:5;padding:10px 14px;color:#fff;background:rgba(32,36,42,0.96);border:1px solid rgba(0,0,0,0.14);border-radius:2px;box-shadow:0 8px 24px rgba(15,23,42,0.24);pointer-events:none;white-space:nowrap;line-height:1.3;transform:translate(-50%,-100%);display:none;';     wrapper.appendChild(tt);     return tt;   }

  const labels = window.camaliganMapData?.labels || {};
  const routes = window.camaliganMapData?.routes || {};
  const restUrl = window.camaliganMapData?.restUrl || '';
  const barangayIds = Object.keys(labels);
  let activeId = '';
  let hoveredId = '';
  let scale = 1;

  const roadNetwork = svg.querySelector('#roadNetwork');
  const boundaries = svg.querySelector('#boundaries');
  if (roadNetwork && boundaries && boundaries.parentNode) {
    boundaries.parentNode.appendChild(roadNetwork);
  }

  if (boundaries) {
    boundaries.style.pointerEvents = 'none';
  }

  function getPathLengthScore(pathData) {
    const tokens = pathData.match(/[MmLlHhVvZz]|-?\d*\.?\d+/g) || [];
    let index = 0;
    let command = '';
    let x = 0;
    let y = 0;
    let startX = 0;
    let startY = 0;
    let total = 0;

    function nextNumber() {
      return Number.parseFloat(tokens[index++]);
    }

    while (index < tokens.length) {
      const token = tokens[index];

      if (/^[MmLlHhVvZz]$/.test(token)) {
        command = token;
        index += 1;

        if (command === 'Z' || command === 'z') {
          total += Math.hypot(startX - x, startY - y);
          x = startX;
          y = startY;
        }

        continue;
      }

      switch (command) {
        case 'M': {
          x = nextNumber();
          y = nextNumber();
          startX = x;
          startY = y;
          command = 'L';
          break;
        }
        case 'm': {
          x += nextNumber();
          y += nextNumber();
          startX = x;
          startY = y;
          command = 'l';
          break;
        }
        case 'L': {
          const nextX = nextNumber();
          const nextY = nextNumber();
          total += Math.hypot(nextX - x, nextY - y);
          x = nextX;
          y = nextY;
          break;
        }
        case 'l': {
          const deltaX = nextNumber();
          const deltaY = nextNumber();
          total += Math.hypot(deltaX, deltaY);
          x += deltaX;
          y += deltaY;
          break;
        }
        case 'H': {
          const nextX = nextNumber();
          total += Math.abs(nextX - x);
          x = nextX;
          break;
        }
        case 'h': {
          const deltaX = nextNumber();
          total += Math.abs(deltaX);
          x += deltaX;
          break;
        }
        case 'V': {
          const nextY = nextNumber();
          total += Math.abs(nextY - y);
          y = nextY;
          break;
        }
        case 'v': {
          const deltaY = nextNumber();
          total += Math.abs(deltaY);
          y += deltaY;
          break;
        }
        default: {
          index += 1;
        }
      }
    }

    return total;
  }

  function applyRoadClasses() {
    if (!roadNetwork) return;

    const roadPaths = Array.from(roadNetwork.querySelectorAll('path.cls-3'));
    if (!roadPaths.length) return;

    const scoredRoads = roadPaths
      .map((path, index) => ({
        path,
        index,
        score: getPathLengthScore(path.getAttribute('d') || '')
      }))
      .sort((left, right) => right.score - left.score);

    const nationalCount = Math.max(2, Math.round(scoredRoads.length * 0.06));
    const provincialCount = Math.max(4, Math.round(scoredRoads.length * 0.12));
    const municipalCount = Math.max(8, Math.round(scoredRoads.length * 0.22));

    scoredRoads.forEach((item, rank) => {
      let roadType = 'barangay';

      if (rank < nationalCount) {
        roadType = 'national';
      } else if (rank < nationalCount + provincialCount) {
        roadType = 'provincial';
      } else if (rank < nationalCount + provincialCount + municipalCount) {
        roadType = 'municipal';
      }

      item.path.classList.add(`cim-road-${roadType}`);
      item.path.dataset.roadType = roadType;
      item.path.dataset.roadRank = String(rank + 1);
      item.path.dataset.roadScore = item.score.toFixed(1);
    });
  }

  function applyScale() {
    mapSurface.style.transform = `scale(${scale})`;
  }

  function setScale(nextScale) {
    scale = Math.min(2.2, Math.max(0.85, nextScale));
    applyScale();
  }

  function fitToFrame() {
    scale = 1;
    applyScale();
    mapViewport.scrollLeft = 0;
    mapViewport.scrollTop = 0;
  }

  function getLabel(id) {
    return labels[id] || id;
  }

  function normalizeRoute(routeValue) {
    const value = (routeValue || '').trim();
    if (!value) return '';

    if (/^(https?:|mailto:|tel:|\/|#|\?)/i.test(value)) {
      return value;
    }

    if (/^(www\.|[a-z0-9-]+\.[a-z]{2,})(\/|$)/i.test(value)) {
      return `https://${value}`;
    }

    return `/${value.replace(/^\/+/, '')}`;
  }

  function getRoute(id, routeValue = '') {
    return normalizeRoute(routeValue || routes[id] || '');
  }

  function getNode(id) {
    return svg.querySelector(`#${CSS.escape(id)}`);
  }

  function syncStates() {
    barangayIds.forEach(id => {
      const node = getNode(id);
      if (!node) return;

      node.classList.toggle('is-active', id === activeId);
      node.classList.toggle('is-hovered', id === hoveredId);
    });
  }

function setTooltip(id, x, y) {     tooltip.textContent = getRoute(id)       ? `Click to open Barangay ${getLabel(id)}'s page`       : `Click here to show Barangay ${getLabel(id)}'s profile`;     tooltip.hidden = false;     tooltip.style.left = `${x}px`;     tooltip.style.top = `${y}px`;   }    function hideTooltip() {     tooltip.hidden = true;   }

  function hideTooltip() {
    tooltip.hidden = true;
  }

function moveTooltip(event) {   if (!hoveredId) return;  const bounds = mapStage.getBoundingClientRect();    const offsetX = event.clientX - bounds.left + 18;    const offsetY = event.clientY - bounds.top - 18 + (mapViewport.scrollTop || 0);     setTooltip(hoveredId, offsetX, offsetY);  }

  function setPreview(id) {
    infoPanel.innerHTML = `
      <h3>${getLabel(id)}</h3>
      <p>${
        getRoute(id)
          ? 'Click this barangay to open its assigned page.'
          : 'Click this barangay to load its profile and keep it highlighted on the map.'
      }</p>
    `;
  }

  function setLoading(id) {
    infoPanel.innerHTML = `
      <h3>${getLabel(id)}</h3>
      <p>Loading barangay data...</p>
    `;
  }

  function setError(id) {
    infoPanel.innerHTML = `
      <h3>${getLabel(id)}</h3>
      <p>Could not load barangay data. Check your REST route or sample dataset.</p>
    `;
  }

  async function fetchBarangayData(id) {
    const response = await fetch(restUrl + encodeURIComponent(id), {
      method: 'GET',
      headers: { 'Accept': 'application/json' }
    });

    if (!response.ok) {
      throw new Error('Request failed');
    }

    return response.json();
  }

  function setData(data) {
    const routeId = data.slug || activeId;
    const route = getRoute(routeId, data.route);

    infoPanel.innerHTML = `
      <h3>${data.name || 'Barangay'}</h3>
      <p><strong>Population:</strong> ${data.population || 'N/A'}</p>
      <p><strong>Barangay Captain:</strong> ${data.captain || 'N/A'}</p>
      <p>${data.description || 'No description available.'}</p>
      <p>
        <button
          type="button"
          class="cim-info-route"
          data-barangay-id="${routeId}"
          data-route="${route}"
          ${route ? '' : 'disabled aria-disabled="true"'}
        >
          Open barangay page
        </button>
      </p>
    `;
  }

  function navigateToBarangayRoute(id, routeValue) {
    const route = getRoute(id, routeValue);
    if (!route) return;

    window.location.href = route;
  }

  async function handleBarangayActivate(id) {
    const route = getRoute(id);
    if (route) {
      activeId = id;
      syncStates();
      if (select) select.value = id;
      navigateToBarangayRoute(id, route);
      return;
    }

    activeId = id;
    syncStates();
    setLoading(id);
    if (select) select.value = id;

    try {
      const data = await fetchBarangayData(id);
      const dataRoute = getRoute(id, data.route || '');

      if (dataRoute) {
        navigateToBarangayRoute(id, dataRoute);
        return;
      }

      setData(data);
    } catch (error) {
      console.error(error);
      setError(id);
    }
  }

  function setHovered(id, event) {
    hoveredId = id;
    syncStates();
    setPreview(id);

    if (event) {
      moveTooltip(event);
    } else {
      setTooltip(id, mapStage.clientWidth / 2, 28);
    }
  }

  function clearHovered() {
    hoveredId = '';
    syncStates();
    hideTooltip();
  }

  async function loadBarangay(id) {
    activeId = id;
    syncStates();
    setLoading(id);
    if (select) select.value = id;

    try {
      const data = await fetchBarangayData(id);
      setData(data);
    } catch (error) {
      console.error(error);
      setError(id);
    }
  }

  barangayIds.forEach(id => {
    const node = getNode(id);
    if (!node) return;

    node.classList.add('cim-barangay');
    node.style.pointerEvents = 'auto';
    node.setAttribute('tabindex', '0');
    node.setAttribute('role', getRoute(id) ? 'link' : 'button');
    node.setAttribute(
      'aria-label',
      getRoute(id) ? `Open ${getLabel(id)} page` : `Show ${getLabel(id)} profile`
    );

    node.addEventListener('pointerenter', (event) => setHovered(id, event));
    node.addEventListener('pointermove', moveTooltip);
    node.addEventListener('pointerleave', () => {
      clearHovered();

      if (activeId) {
        setPreview(activeId);
      }
    });
    node.addEventListener('focus', () => setHovered(id));
    node.addEventListener('blur', () => {
      clearHovered();

      if (activeId) {
        setPreview(activeId);
      }
    });
    node.addEventListener('click', () => handleBarangayActivate(id));
    node.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        handleBarangayActivate(id);
      }
    });
  });

  if (select) {
    select.addEventListener('change', function () {
      if (this.value) handleBarangayActivate(this.value);
    });
  }

  infoPanel.addEventListener('click', (event) => {
    const routeButton = event.target.closest('.cim-info-route');
    if (!routeButton) return;

    navigateToBarangayRoute(
      routeButton.getAttribute('data-barangay-id') || activeId,
      routeButton.getAttribute('data-route') || ''
    );
  });

  if (zoomInButton) {
    zoomInButton.addEventListener('click', () => setScale(scale + 0.2));
  }

  if (zoomOutButton) {
    zoomOutButton.addEventListener('click', () => setScale(scale - 0.2));
  }

  if (fitButton) {
    fitButton.addEventListener('click', fitToFrame);
  }

  applyRoadClasses();
  applyScale();
});
