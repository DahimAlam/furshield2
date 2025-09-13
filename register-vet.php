<?php
session_start();
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auth.php";

$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => '', 'specialization' => '', 'experience' => '', 'clinic' => '', 'city' => '', 'country' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $specialization = trim($_POST['specialization'] ?? '');
  $experience = (int)($_POST['experience'] ?? 0);
  $clinic = trim($_POST['clinic'] ?? '');
  $city = trim($_POST['city'] ?? '');
  $country = trim($_POST['country'] ?? '');
  $pass = (string)($_POST['password'] ?? '');
  $cpass = (string)($_POST['confirm_password'] ?? '');
  $terms = isset($_POST['terms']) ? 1 : 0;

  $old = compact('name', 'email', 'phone', 'specialization', 'experience', 'clinic', 'city', 'country');

  if (strlen($name) < 2) $errors[] = "Please enter your full name.";
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Please enter a valid email.";
  if (strlen($phone) < 6) $errors[] = "Please enter a valid phone number.";
  if (!$specialization) $errors[] = "Please enter specialization.";
  if ($experience <= 0) $errors[] = "Please enter valid years of experience.";
  if (!$clinic) $errors[] = "Please enter your clinic address.";
  if (!$city) $errors[] = "Please select your city.";
  if (!$country) $errors[] = "Please select your country.";
  if (strlen($pass) < 6) $errors[] = "Password must be at least 6 characters.";
  if ($pass !== $cpass) $errors[] = "Passwords do not match.";
  if (!$terms) $errors[] = "Please accept the Terms & Privacy Policy.";

  // File validation
  $profileImg = $_FILES['profile_image'] ?? null;
  $cnicImg = $_FILES['cnic_image'] ?? null;
  if (!$profileImg || $profileImg['error'] !== UPLOAD_ERR_OK) $errors[] = "Please upload profile image.";
  if (!$cnicImg || $cnicImg['error'] !== UPLOAD_ERR_OK) $errors[] = "Please upload CNIC image.";

  // Duplicate email check
  if (!$errors) {
    $stmt = $conn->prepare("SELECT 1 FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_row();
    if ($exists) $errors[] = "An account with this email already exists.";
  }

  if (!$errors) {
    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $role = 'vet';
    $status = 'pending'; // vets need admin approval
    $stmt = $conn->prepare("INSERT INTO users(role,name,email,phone,pass_hash,status) VALUES(?,?,?,?,?,?)");
    $stmt->bind_param("ssssss", $role, $name, $email, $phone, $hash, $status);
    $stmt->execute();
    $user_id = $stmt->insert_id;

    // Upload images
    $profilePath = "uploads/vets/profile_" . $user_id . ".jpg";
    $cnicPath = "uploads/vets/cnic_" . $user_id . ".jpg";
    move_uploaded_file($profileImg['tmp_name'], __DIR__ . "/" . $profilePath);
    move_uploaded_file($cnicImg['tmp_name'], __DIR__ . "/" . $cnicPath);

    // Insert into vets
    $stmt2 = $conn->prepare("INSERT INTO vets(user_id, specialization, experience_years, clinic_address, city, country, profile_image, cnic_image) VALUES(?,?,?,?,?,?,?,?)");
    $stmt2->bind_param("isisssss", $user_id, $specialization, $experience, $clinic, $city, $country, $profilePath, $cnicPath);
    $stmt2->execute();

    header("Location: " . BASE . "/login.php?registered=1&pending=1");
    exit;
  }
}

include __DIR__ . "/includes/header.php";
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/css/intlTelInput.css" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/intlTelInput.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

<div class="row justify-content-center">
  <div class="col-md-8 col-lg-7">
    <div class="card card-soft p-4 p-md-5">
      <div class="d-flex align-items-center mb-3">
        <span class="logo-badge me-2"><i class="bi bi-heart-pulse text-white"></i></span>
        <h1 class="h4 mb-0 brand-text">Register as Vet</h1>
      </div>

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
        </div>
        
      <?php endif; ?>
      <div class="mb-5">
        <h2 class="h5 fw-bold mb-3"><i class="bi bi-info-circle text-primary"></i> How to Register as a Vet</h2>
        <ul class="timeline-vertical">
          <li class="timeline-item success">
            <span class="dot"></span>
            <div class="timeline-card">
              <div class="d-flex align-items-center gap-2">
                <span class="icon-circle success"><i class="bi bi-person-plus"></i></span>
                <div>
                  <div class="fw-semibold">Fill the form</div>
                  <div class="timeline-meta">Provide personal and clinic details.</div>
                </div>
              </div>
            </div>
          </li>
          <li class="timeline-item active">
            <span class="dot"></span>
            <div class="timeline-card">
              <div class="d-flex align-items-center gap-2">
                <span class="icon-circle"><i class="bi bi-shield-check"></i></span>
                <div>
                  <div class="fw-semibold">Admin review (up to 48h)</div>
                  <div class="timeline-meta">We’ll notify you by email.</div>
                </div>
              </div>
            </div>
          </li>
          <li class="timeline-item">
            <span class="dot"></span>
            <div class="timeline-card">
              <div class="d-flex align-items-center gap-2">
                <span class="icon-circle accent"><i class="bi bi-envelope"></i></span>
                <div>
                  <div class="fw-semibold">Approval email</div>
                  <div class="timeline-meta">Immediate access after approval.</div>
                </div>
              </div>
            </div>
          </li>
          <li class="timeline-item">
            <span class="dot"></span>
            <div class="timeline-card">
              <div class="d-flex align-items-center gap-2">
                <span class="icon-circle"><i class="bi bi-box-arrow-in-right"></i></span>
                <div>
                  <div class="fw-semibold">Log in & manage appointments</div>
                  <div class="timeline-meta">Access your vet dashboard.</div>
                </div>
              </div>
            </div>
          </li>
        </ul>
      </div>

      <form method="post" enctype="multipart/form-data" novalidate>
        <div class="mb-3">
          <label class="form-label">Full name</label>
          <input name="name" class="form-control" required value="<?php echo htmlspecialchars($old['name']); ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($old['email']); ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Phone</label>
          <input id="phone" type="tel" name="phone" class="form-control" required value="<?php echo htmlspecialchars($old['phone']); ?>">
        </div>
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label">Country</label>
            <select id="country" name="country" class="form-select" required>
              <option value="">Select Country</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">City</label>
            <select id="city" name="city" class="form-select" required>
              <option value="">Select City</option>
            </select>
          </div>
        </div>
        <div class="mb-3 mt-2">
          <label class="form-label">Specialization</label>
          <input name="specialization" class="form-control" required value="<?php echo htmlspecialchars($old['specialization']); ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Years of Experience</label>
          <input type="number" name="experience" class="form-control" required value="<?php echo htmlspecialchars($old['experience']); ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Clinic Address</label>
          <input name="clinic" class="form-control" required value="<?php echo htmlspecialchars($old['clinic']); ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Profile Image</label>
          <input type="file" name="profile_image" class="form-control" accept="image/*" required>
        </div>
        <div class="mb-3">
          <label class="form-label">CNIC / License Image</label>
          <input type="file" name="cnic_image" class="form-control" accept="image/*" required>
        </div>

        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label">Password</label>
            <input type="password" name="password" id="pwd1" class="form-control" required placeholder="Create password" oninput="strength();">
          </div>
          <div class="col-md-6">
            <label class="form-label">Confirm password</label>
            <input type="password" name="confirm_password" id="pwd2" class="form-control" required placeholder="Repeat password">
          </div>
        </div>
        <div class="small text-muted mt-1" id="pwdStrength">Password strength: —</div>

        <div class="form-check my-3">
          <input class="form-check-input" type="checkbox" value="1" id="terms" name="terms" required>
          <label class="form-check-label" for="terms">I agree to the <a href="#">Terms</a> & <a href="#">Privacy Policy</a>.</label>
        </div>

        <button class="btn btn-primary w-100">Register as Vet</button>
      </form>
    </div>
  </div>
</div>

<script>
  function strength() {
    const v = document.getElementById('pwd1').value;
    let score = 0;
    if (v.length >= 6) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const s = ['—', 'Weak', 'Okay', 'Good', 'Strong'][score];
    document.getElementById('pwdStrength').textContent = 'Password strength: ' + s;
  }

  const phoneInput = document.querySelector("#phone");
  const iti = window.intlTelInput(phoneInput, {
    initialCountry: "pk",
    separateDialCode: true,
    utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/utils.js"
  });

  // Country-city dataset
  const countryCity = {
    "Pakistan": ["Karachi", "Lahore", "Islamabad"],
    "India": ["Delhi", "Mumbai", "Bangalore"],
    "United States": ["New York", "Los Angeles", "Chicago"],
    "United Kingdom": ["London", "Manchester", "Birmingham"]
  };

  for (let country in countryCity) {
    $('#country').append(new Option(country, country));
  }

  $('#country').on('change', function() {
    $('#city').empty();
    let c = $(this).val();
    if (countryCity[c]) {
      countryCity[c].forEach(city => {
        $('#city').append(new Option(city, city));
      });
    }
  });

  document.querySelector("form").addEventListener("submit", function(e) {
    if (!iti.isValidNumber()) {
      e.preventDefault();
      alert("Please enter a valid phone number.");
      phoneInput.focus();
      return false;
    }
    const hiddenInput = document.createElement("input");
    hiddenInput.type = "hidden";
    hiddenInput.name = "phone";
    hiddenInput.value = iti.getNumber();
    this.appendChild(hiddenInput);
    phoneInput.disabled = true;
  });
</script>
<?php include __DIR__ . "/includes/footer.php"; ?>