<?php
$path = basename($_SERVER['PHP_SELF']);
function active($p,$path){ return $p===$path?' active':''; }
?>
<aside class="sidebar">
  <nav class="menu">
    <a class="menu-item<?php echo active('dashboard.php',$path); ?>" href="dashboard.php"><i class="bi bi-activity"></i><span>Dashboard</span></a>
    <a class="menu-item" href="appointments.php"><i class="bi bi-calendar-check"></i><span>Appointments</span></a>
    <a class="menu-item" href="records.php"><i class="bi bi-file-medical"></i><span>Medical Records</span></a>
    <a class="menu-item" href="availability.php"><i class="bi bi-clock-history"></i><span>Availability</span></a>
    <a class="menu-item" href="reviews.php"><i class="bi bi-stars"></i><span>Reviews</span></a>
    <a class="menu-item" href="profile.php"><i class="bi bi-person-gear"></i><span>Profile</span></a>
  </nav>
</aside>
