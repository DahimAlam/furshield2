<?php
session_start();
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auth.php";

$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => '', 'city' => '', 'country' => '', 'adopt' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $city = trim($_POST['city'] ?? '');
  $country = trim($_POST['country'] ?? '');
  $adopt = isset($_POST['adopt']) ? 1 : 0;
  $pass = (string)($_POST['password'] ?? '');
  $cpass = (string)($_POST['confirm_password'] ?? '');
  $terms = isset($_POST['terms']) ? 1 : 0;

  $old = compact('name', 'email', 'phone', 'city', 'country');
  $old['adopt'] = $adopt;

  if (strlen($name) < 2) $errors[] = "Please enter your full name.";
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Please enter a valid email.";
  if (strlen($phone) < 6) $errors[] = "Please enter a valid phone number.";
  if (!$city) $errors[] = "Please select your city.";
  if (!$country) $errors[] = "Please select your country.";
  if (strlen($pass) < 6) $errors[] = "Password must be at least 6 characters.";
  if ($pass !== $cpass) $errors[] = "Passwords do not match.";
  if (!$terms) $errors[] = "Please accept the Terms & Privacy Policy.";

  if (!$errors) {
    $stmt = $conn->prepare("SELECT 1 FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_row();
    if ($exists) $errors[] = "An account with this email already exists.";
  }

  if (!$errors) {
    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $role = 'owner';
    $status = 'active';
    $stmt = $conn->prepare("INSERT INTO users(role,name,email,phone,pass_hash,status) VALUES(?,?,?,?,?,?)");
    $stmt->bind_param("ssssss", $role, $name, $email, $phone, $hash, $status);
    $stmt->execute();
    $user_id = $stmt->insert_id;

    $stmt2 = $conn->prepare("INSERT INTO owners(user_id, full_name, email, phone, address, city, country, adopt_interest) 
                         VALUES(?,?,?,?,?,?,?,?)");
    $stmt2->bind_param("issssssi", $user_id, $name, $email, $phone, $address, $city, $country, $adopt);
    $stmt2->execute();


    header("Location: " . BASE . "/login.php?registered=1");
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
  <div class="col-md-7 col-lg-6">
    <div class="card card-soft p-4 p-md-5">
      <div class="d-flex align-items-center mb-3">
        <span class="logo-badge me-2"><i class="bi bi-shield-heart text-white"></i></span>
        <h1 class="h4 mb-0 brand-text">Create your Owner account</h1>
      </div>

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <ul class="mb-0">
            <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" novalidate>
        <div class="mb-3">
          <label class="form-label">Full name</label>
          <input name="name" class="form-control" required value="<?php echo htmlspecialchars($old['name']); ?>" placeholder="Ayesha Khan">
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($old['email']); ?>" placeholder="you@example.com">
        </div>
        <div class="mb-3">
          <label class="form-label">Phone (with country code)</label>
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

        <div class="row g-2 mt-2">
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
          <input class="form-check-input" type="checkbox" name="adopt" value="1" id="adopt" <?php if ($old['adopt']) echo "checked"; ?>>
          <label class="form-check-label" for="adopt">I am interested in adopting a pet</label>
        </div>

        <div class="form-check my-3">
          <input class="form-check-input" type="checkbox" value="1" id="terms" name="terms" required>
          <label class="form-check-label" for="terms">
            I agree to the <a href="#" class="link-muted">Terms</a> and <a href="#" class="link-muted">Privacy Policy</a>.
          </label>
        </div>

        <button class="btn btn-primary w-100">Create Account</button>
      </form>

      <div class="mt-3 d-flex justify-content-between">
        <a class="small link-muted" href="<?php echo BASE; ?>/login.php">Already have an account? Log in</a>
        <a class="small link-muted" href="<?php echo BASE; ?>/register-vet.php">Register as Vet</a>
      </div>
    </div>
  </div>
</div>

<script>
// Password strength checker
function strength(){
  const v = document.getElementById('pwd1').value;
  let score = 0;
  if(v.length >= 6) score++;
  if(/[A-Z]/.test(v)) score++;
  if(/[0-9]/.test(v)) score++;
  if(/[^A-Za-z0-9]/.test(v)) score++;
  const s = ['—','Weak','Okay','Good','Strong'][score];
  document.getElementById('pwdStrength').textContent = 'Password strength: ' + s;
}

// Intl phone input setup
const phoneInput = document.querySelector("#phone");
const iti = window.intlTelInput(phoneInput, {
  initialCountry: "pk",
  separateDialCode: true,
  utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/utils.js"
});

// Static dataset (demo countries + cities)
const countryCity = {
  "Pakistan": ["Karachi","Lahore","Islamabad","Multan","Peshawar"],
  "India": ["Delhi","Mumbai","Bangalore","Chennai"],
  "United States": ["New York","Los Angeles","Chicago","Houston"],
  "United Kingdom": ["London","Manchester","Birmingham","Liverpool"],
  "Canada": ["Toronto","Vancouver","Montreal","Calgary"],
  "Australia": ["Sydney","Melbourne","Perth","Brisbane"]
};

// Populate country dropdown
for (let country in countryCity) {
  $('#country').append(new Option(country, country));
}

// Enable select2 search
$('#country, #city').select2({
  placeholder: "Select...",
  allowClear: true
});

// Load cities on country change
$('#country').on('change', function(){
  $('#city').empty().trigger('change');
  let country = $(this).val();
  if (country && countryCity[country]) {
    countryCity[country].forEach(city => {
      $('#city').append(new Option(city, city));
    });
  }
});

// Form validation (phone check)
document.querySelector("form").addEventListener("submit", function(e){
  if (!iti.isValidNumber()) {
    e.preventDefault();
    alert("Please enter a valid phone number.");
    phoneInput.focus();
    return false;
  } else {
    const phoneHidden = document.createElement("input");
    phoneHidden.type = "hidden";
    phoneHidden.name = "phone";
    phoneHidden.value = iti.getNumber(); 
    this.appendChild(phoneHidden);
  }
});

</script>

<?php include __DIR__ . "/includes/footer.php"; ?>