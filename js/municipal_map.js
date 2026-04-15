(function(){
  function initApp(app) {
    if (!app || app.dataset.mmocReady === '1') return;
    app.dataset.mmocReady = '1';

    const viewerFrame = app.querySelector('.mmoc-viewer-frame');
    const mainGraphic = app.querySelector('.mmoc-svg-layer-base');
    const controlGroups = app.querySelectorAll('[data-mmoc-controls]');
    const buildingLayers = app.querySelectorAll('.mmoc-building-layer');
    const officeHoverZones = app.querySelectorAll('.mmoc-office-hover-zone');
    const baseHoverTargets = app.querySelectorAll('.mmoc-base-hover-target');
    const hotspotLinks = app.querySelectorAll('.mmoc-stair-link');
    const buildingIndicators = app.querySelectorAll('.mmoc-building-indicator');
    const officeLinkMap = window.mmocOfficeLinks || {};
    const buildingFloorsByArea = { left:[1,2,3], other:[1,2], 'upper-right':[1,2,3], 'lower-right':[1,2] };
    let svgWidth = 1440, svgHeight = 1024, zoomLevel = 3, fitScale = 3;
    const activeFloors = { left:1, other:1, 'upper-right':1, 'lower-right':1 };
    const panState = { pointerId:null, startX:0, startY:0, startLeft:0, startTop:0, moved:false, suppressClick:false };
    const cleanup = [];

    if (!viewerFrame || !mainGraphic) return;

    function getFrameViewportSize(){
      const computedStyle = window.getComputedStyle(viewerFrame);
      const horizontalPadding = parseFloat(computedStyle.paddingLeft || '0') + parseFloat(computedStyle.paddingRight || '0');
      const verticalPadding = parseFloat(computedStyle.paddingTop || '0') + parseFloat(computedStyle.paddingBottom || '0');

      return {
        width: viewerFrame.clientWidth - horizontalPadding,
        height: viewerFrame.clientHeight - verticalPadding,
      };
    }

    function updatePanState(){
      const visibleScale = fitScale * zoomLevel;
      const { width: frameWidth, height: frameHeight } = getFrameViewportSize();
      const stageWidth = svgWidth * visibleScale;
      const stageHeight = svgHeight * visibleScale;
      const isPannable = frameWidth > 0 && frameHeight > 0 && (stageWidth > frameWidth + 1 || stageHeight > frameHeight + 1);

      viewerFrame.classList.toggle('is-pannable', isPannable);

      if (!isPannable) {
        viewerFrame.classList.remove('is-grabbing');
        panState.pointerId = null;
        panState.moved = false;
      }
    }

    function renderScale(){
      const visibleScale = fitScale * zoomLevel;
      app.style.setProperty('--stage-width', `${(svgWidth * visibleScale).toFixed(3)}px`);
      app.style.setProperty('--stage-height', `${(svgHeight * visibleScale).toFixed(3)}px`);
      updatePanState();
    }

    function renderActiveFloors(){
      buildingLayers.forEach((layer)=>{
        const building = layer.dataset.building;
        const floor = Number(layer.dataset.floor);
        layer.classList.toggle('is-visible', activeFloors[building] === floor);
      });
      hotspotLinks.forEach((link)=>{
        const building = link.dataset.building;
        const floor = Number(link.dataset.floor);
        link.classList.toggle('is-active', activeFloors[building] === floor);
      });
      buildingIndicators.forEach((indicator)=>{
        indicator.dataset.activeFloor = String(activeFloors[indicator.dataset.building]);
      });
      officeHoverZones.forEach((zone) => {
        const building = zone.dataset.building;
        const floor = Number(zone.dataset.floor);
        const isScopedZone = building && floor;

        zone.classList.toggle('is-active', !isScopedZone || activeFloors[building] === floor);
      });
    }

    function calculateFitScale(){
      const { width: frameWidth, height: frameHeight } = getFrameViewportSize();

      if (frameWidth <= 0 || frameHeight <= 0) {
        return false;
      }

      fitScale = Math.min((frameHeight / svgHeight) * 1.08, frameWidth / svgWidth, 1);
      return true;
    }

    function centerStageInView(){
      const { width: frameWidth, height: frameHeight } = getFrameViewportSize();
      const visibleScale = fitScale * zoomLevel;
      const stageWidth = svgWidth * visibleScale;
      const stageHeight = svgHeight * visibleScale;
      const centeredLeft = Math.max((stageWidth - frameWidth) / 2, 0);
      const centeredTop = Math.max((stageHeight - frameHeight) / 2, 0);

      viewerFrame.scrollTo({ left: centeredLeft, top: centeredTop, behavior: 'auto' });
    }

    function fitToFrame(){
      syncSvgDimensions();
      if (!calculateFitScale()) return;
      zoomLevel = 1;
      renderScale();
      requestAnimationFrame(centerStageInView);
    }

    function syncSvgDimensions(){
      if (mainGraphic instanceof HTMLImageElement) {
        if (mainGraphic.naturalWidth > 0 && mainGraphic.naturalHeight > 0) {
          svgWidth = mainGraphic.naturalWidth;
          svgHeight = mainGraphic.naturalHeight;
        }
        return;
      }

      const viewBox = typeof mainGraphic.getAttribute === 'function'
        ? (mainGraphic.getAttribute('viewBox') || '').trim()
        : '';

      if (viewBox) {
        const parts = viewBox.split(/[\s,]+/).map(Number);
        if (parts.length === 4 && Number.isFinite(parts[2]) && Number.isFinite(parts[3]) && parts[2] > 0 && parts[3] > 0) {
          svgWidth = parts[2];
          svgHeight = parts[3];
          return;
        }
      }

      const width = Number(mainGraphic.getAttribute('width'));
      const height = Number(mainGraphic.getAttribute('height'));
      if (Number.isFinite(width) && Number.isFinite(height) && width > 0 && height > 0) {
        svgWidth = width;
        svgHeight = height;
      }
    }

    function setScale(nextScale){
      zoomLevel = Math.min(3, Math.max(0.5, nextScale));
      renderScale();
    }

    function stopPanning(){
      if (panState.pointerId === null) return;

      panState.pointerId = null;
      viewerFrame.classList.remove('is-grabbing');

      if (panState.moved) {
        panState.suppressClick = true;
        window.setTimeout(() => {
          panState.suppressClick = false;
        }, 0);
      }

      panState.moved = false;
    }

    function isInteractiveTarget(target){
      if (!(target instanceof Element)) return false;

      return Boolean(target.closest(
        '.mmoc-office-hover-zone, .mmoc-stair-link, .mmoc-map-controls, .mmoc-map-control, button, a'
      ));
    }

    function getSupportedFloor(building, requestedFloor){
      const availableFloors = buildingFloorsByArea[building];
      if (availableFloors.includes(requestedFloor)) return requestedFloor;
      const lowerFloors = availableFloors.filter((floor)=> floor <= requestedFloor);
      return lowerFloors[lowerFloors.length - 1] ?? availableFloors[0];
    }

    function setBuildingFloor(building, nextFloor){
      activeFloors[building] = getSupportedFloor(building, nextFloor);
      renderActiveFloors();
    }

    function getOfficeLinkConfig(zone){
      const officeId = zone.dataset.office;
      if (!officeId) return null;

      const configuredValue = officeLinkMap[officeId];
      if (!configuredValue) return null;

      if (typeof configuredValue === 'string') {
        const url = configuredValue.trim();
        return url ? { url, target:'_self' } : null;
      }

      if (typeof configuredValue === 'object') {
        const url = typeof configuredValue.url === 'string' ? configuredValue.url.trim() : '';
        if (!url) return null;

        return {
          url,
          target: configuredValue.target === '_blank' ? '_blank' : '_self',
        };
      }

      return null;
    }

    function openOfficeLink(zone){
      const officeLink = getOfficeLinkConfig(zone);
      if (!officeLink) return false;

      if (officeLink.target === '_blank') {
        window.open(officeLink.url, '_blank', 'noopener');
      } else {
        window.location.assign(officeLink.url);
      }

      return true;
    }

    controlGroups.forEach((controls) => {
      controls.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const action = button.getAttribute('data-action');
        if (action === 'zoom-in') setScale(zoomLevel + 0.1);
        if (action === 'zoom-out') setScale(zoomLevel - 0.1);
        if (action === 'reset') fitToFrame();
      });
    });

    hotspotLinks.forEach((link)=>{
      link.addEventListener('click', (event)=>{
        event.preventDefault();
        setBuildingFloor(link.dataset.building, Number(link.dataset.targetFloor));
      });
    });

    officeHoverZones.forEach((zone) => {
      const hoverGroup = zone.dataset.hoverGroup;
      const officeLink = getOfficeLinkConfig(zone);

      zone.classList.toggle('is-clickable', Boolean(officeLink));

      if (officeLink) {
        zone.setAttribute('role', 'link');
        zone.setAttribute('tabindex', '0');
      } else if (!zone.classList.contains('mmoc-office-hover-zone-passive')) {
        zone.removeAttribute('role');
        zone.setAttribute('tabindex', '-1');
      }

      const groupedZones = hoverGroup
        ? app.querySelectorAll(`.mmoc-office-hover-zone[data-hover-group="${hoverGroup}"]`)
        : [zone];
      const setGroupedHover = (isHovered) => {
        groupedZones.forEach((groupedZone) => {
          groupedZone.classList.toggle('is-hovered', isHovered);
        });
      };

      const onEnter = () => setGroupedHover(true);
      const onLeave = () => setGroupedHover(false);
      const onClick = (event) => {
        if (!openOfficeLink(zone)) return;
        event.preventDefault();
      };
      const onKeyDown = (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        if (!openOfficeLink(zone)) return;
        event.preventDefault();
      };

      zone.addEventListener('mouseenter', onEnter);
      zone.addEventListener('mouseleave', onLeave);
      zone.addEventListener('focus', onEnter);
      zone.addEventListener('blur', onLeave);
      zone.addEventListener('click', onClick);
      zone.addEventListener('keydown', onKeyDown);

      cleanup.push(() => {
        zone.removeEventListener('mouseenter', onEnter);
        zone.removeEventListener('mouseleave', onLeave);
        zone.removeEventListener('focus', onEnter);
        zone.removeEventListener('blur', onLeave);
        zone.removeEventListener('click', onClick);
        zone.removeEventListener('keydown', onKeyDown);
      });
    });

    baseHoverTargets.forEach((target) => {
      const setHovered = (isHovered) => {
        target.classList.toggle('is-hovered', isHovered);
      };

      target.addEventListener('mouseenter', () => setHovered(true));
      target.addEventListener('mouseleave', () => setHovered(false));
      target.addEventListener('focus', () => setHovered(true));
      target.addEventListener('blur', () => setHovered(false));
    });

    const onPointerDown = (event) => {
      if (!viewerFrame.classList.contains('is-pannable')) return;
      if (typeof event.button === 'number' && event.button !== 0) return;
      if (isInteractiveTarget(event.target)) return;

      panState.pointerId = event.pointerId;
      panState.startX = event.clientX;
      panState.startY = event.clientY;
      panState.startLeft = viewerFrame.scrollLeft;
      panState.startTop = viewerFrame.scrollTop;
      panState.moved = false;
      viewerFrame.classList.add('is-grabbing');

      if (typeof viewerFrame.setPointerCapture === 'function') {
        viewerFrame.setPointerCapture(event.pointerId);
      }

      event.preventDefault();
    };

    const onPointerMove = (event) => {
      if (panState.pointerId !== event.pointerId) return;

      const deltaX = event.clientX - panState.startX;
      const deltaY = event.clientY - panState.startY;

      if (!panState.moved && Math.hypot(deltaX, deltaY) > 4) {
        panState.moved = true;
      }

      viewerFrame.scrollLeft = panState.startLeft - deltaX;
      viewerFrame.scrollTop = panState.startTop - deltaY;
      event.preventDefault();
    };

    const onPointerUp = (event) => {
      if (panState.pointerId !== event.pointerId) return;

      if (typeof viewerFrame.releasePointerCapture === 'function' && viewerFrame.hasPointerCapture && viewerFrame.hasPointerCapture(event.pointerId)) {
        viewerFrame.releasePointerCapture(event.pointerId);
      }

      stopPanning();
    };

    const onPointerCancel = () => {
      stopPanning();
    };

    const onClickCapture = (event) => {
      if (!panState.suppressClick) return;

      event.preventDefault();
      event.stopPropagation();
      panState.suppressClick = false;
    };

    viewerFrame.addEventListener('pointerdown', onPointerDown);
    viewerFrame.addEventListener('pointermove', onPointerMove);
    viewerFrame.addEventListener('pointerup', onPointerUp);
    viewerFrame.addEventListener('pointercancel', onPointerCancel);
    viewerFrame.addEventListener('lostpointercapture', onPointerCancel);
    viewerFrame.addEventListener('click', onClickCapture, true);

    cleanup.push(() => viewerFrame.removeEventListener('pointerdown', onPointerDown));
    cleanup.push(() => viewerFrame.removeEventListener('pointermove', onPointerMove));
    cleanup.push(() => viewerFrame.removeEventListener('pointerup', onPointerUp));
    cleanup.push(() => viewerFrame.removeEventListener('pointercancel', onPointerCancel));
    cleanup.push(() => viewerFrame.removeEventListener('lostpointercapture', onPointerCancel));
    cleanup.push(() => viewerFrame.removeEventListener('click', onClickCapture, true));

    function syncViewer(){
      syncSvgDimensions();
      if (calculateFitScale()) {
        renderScale();
      }
      renderActiveFloors();
    }

    if (mainGraphic instanceof HTMLImageElement && !mainGraphic.complete) {
      mainGraphic.addEventListener('load', fitToFrame, { once: true });
    } else {
      fitToFrame();
    }

    if (typeof AbortController === 'function') {
      const resizeController = new AbortController();
      window.addEventListener('resize', syncViewer, { signal: resizeController.signal });
      cleanup.push(() => resizeController.abort());
    } else {
      window.addEventListener('resize', syncViewer);
      cleanup.push(() => window.removeEventListener('resize', syncViewer));
    }

    if (typeof ResizeObserver === 'function') {
      const resizeObserver = new ResizeObserver(syncViewer);
      resizeObserver.observe(viewerFrame);
      cleanup.push(() => resizeObserver.disconnect());
    }

    app._mmocCleanup = () => {
      cleanup.forEach((runCleanup) => runCleanup());
      delete app._mmocCleanup;
      delete app.dataset.mmocReady;
    };
  }

  function initAll(root) {
    const apps = root.querySelectorAll ? root.querySelectorAll('.mmoc-app-shell') : [];
    apps.forEach(initApp);
    if (root.matches && root.matches('.mmoc-app-shell')) {
      initApp(root);
    }
  }

  function destroyAll(root) {
    const apps = root.querySelectorAll ? root.querySelectorAll('.mmoc-app-shell') : [];
    apps.forEach((app) => {
      if (typeof app._mmocCleanup === 'function') {
        app._mmocCleanup();
      }
    });
    if (root.matches && root.matches('.mmoc-app-shell') && typeof root._mmocCleanup === 'function') {
      root._mmocCleanup();
    }
  }

  initAll(document);

  if (typeof MutationObserver === 'function' && document.body) {
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
          if (node.nodeType === 1) {
            initAll(node);
          }
        });
        mutation.removedNodes.forEach((node) => {
          if (node.nodeType === 1) {
            destroyAll(node);
          }
        });
      });
    });

    observer.observe(document.body, { childList: true, subtree: true });
  }
})();
