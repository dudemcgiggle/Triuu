document.addEventListener('DOMContentLoaded', function () {
  var container = document.querySelector('.hec-pf-icon');
  if (!container || typeof lottie === 'undefined') return;

  lottie.loadAnimation({
    container: container,
    renderer: 'svg',
    loop: true,
    autoplay: true,
    path: window.themeAssetsUrl + 'lock/lock.json'
  });
});
