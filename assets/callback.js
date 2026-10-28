// KingsChat returns the token in the URL hash on GET; post it to the server, which fetches the profile itself.
(() => {
  const params = new URLSearchParams(location.hash.replace(/^#/, ''));
  const token = params.get('accessToken') || params.get('access_token');
  history.replaceState(null, '', location.pathname);
  if (!token) { location.replace(location.pathname.replace(/\/auth\/callback$/, '/login?error=kc')); return; }
  const form = document.querySelector('[data-kc-bridge]');
  form.accessToken.value = token;
  form.submit();
})();
