document.addEventListener('DOMContentLoaded', () => {
  const input = document.getElementById('searchInput');
  const box = document.getElementById('suggestionBox');

  input.addEventListener('input', function() {
    const q = this.value.trim();
    if (q.length < 2) {
      box.innerHTML = '';
      box.style.display = 'none';
      return;
    }

    fetch(`/furshield/search-suggest.php?q=${encodeURIComponent(q)}`)
      .then(res => res.json())
      .then(data => {
        if (!data.length) {
          box.style.display = 'none';
          return;
        }

        box.innerHTML = data.map(item =>
          `<a href="/furshield/pet.php?id=${item.id}" class="suggestion-item">${item.name}</a>`
        ).join('');
        box.style.display = 'block';
      });
  });

  document.addEventListener('click', e => {
    if (!box.contains(e.target) && e.target !== input) {
      box.style.display = 'none';
    }
  });
});
