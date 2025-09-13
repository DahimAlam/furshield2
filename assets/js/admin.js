(function () {
  if (!window.DASHBOARD_DATA) return;
  const { labels, signups, roles } = window.DASHBOARD_DATA;

  const signupsCtx = document.getElementById('signupChart');
  if (signupsCtx) {
    new Chart(signupsCtx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Signups',
          data: signups,
          tension: 0.35,
          fill: false
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
      }
    });
  }

  const rolesCtx = document.getElementById('rolesChart');
  if (rolesCtx) {
    new Chart(rolesCtx, {
      type: 'doughnut',
      data: {
        labels: ['Owners', 'Vets', 'Shelters', 'Admins'],
        datasets: [{
          data: [roles.owner||0, roles.vet||0, roles.shelter||0, roles.admin||0]
        }]
      },
      options: { responsive: true, cutout: '60%' }
    });
  }
})();
