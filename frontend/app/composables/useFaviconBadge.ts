export function useFaviconBadge(count: MaybeRefOrGetter<number>) {
  if (!import.meta.client) return;

  const originalHref = '/favicon.svg';
  let faviconLink: HTMLLinkElement | null = null;
  let baseImage: HTMLImageElement | null = null;
  let imageLoaded = false;

  function getFaviconLink(): HTMLLinkElement {
    if (!faviconLink) {
      faviconLink = document.querySelector<HTMLLinkElement>('link[rel="icon"]');
      if (!faviconLink) {
        faviconLink = document.createElement('link');
        faviconLink.rel = 'icon';
        document.head.appendChild(faviconLink);
      }
    }
    return faviconLink;
  }

  function loadBaseImage(): Promise<void> {
    return new Promise((resolve) => {
      if (imageLoaded && baseImage) {
        resolve();
        return;
      }
      baseImage = new Image();
      baseImage.onload = () => {
        imageLoaded = true;
        resolve();
      };
      baseImage.onerror = () => resolve();
      baseImage.src = originalHref;
    });
  }

  async function updateFavicon(n: number) {
    const link = getFaviconLink();

    if (n <= 0) {
      link.href = originalHref;
      if ('clearAppBadge' in navigator) (navigator as any).clearAppBadge();
      return;
    }

    await loadBaseImage();
    if (!baseImage || !imageLoaded) return;

    const canvas = document.createElement('canvas');
    canvas.width = 32;
    canvas.height = 32;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    // Ensure white circular background (canvas is transparent by default)
    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.arc(16, 16, 16, 0, Math.PI * 2);
    ctx.fill();

    ctx.drawImage(baseImage, 0, 0, 32, 32);

    // Red dot badge — top-right corner
    const r = 7;
    const x = 32 - r;
    const y = r;
    ctx.beginPath();
    ctx.arc(x, y, r, 0, Math.PI * 2);
    ctx.fillStyle = '#ef4444';
    ctx.fill();

    link.href = canvas.toDataURL('image/png');

    if ('setAppBadge' in navigator) (navigator as any).setAppBadge(n);
  }

  watch(
    () => toValue(count),
    (n) => updateFavicon(n),
    { immediate: true }
  );
}
