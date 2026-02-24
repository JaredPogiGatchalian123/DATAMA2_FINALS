<?php
include 'config/db.php';
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    header("Location: customer_login.php");
    exit();
}

$selectedVet = $_GET['vet_id'] ?? null;
$owner_id = $_SESSION['owner_id']; // Get the current owner's ID
?>
<!DOCTYPE html>
<html>
<head>
    <title>Book Appointment | HappyPaws</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .booking-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(15px);
            border-radius: 30px;
            padding: 45px;
            width: 100%;
            max-width: 850px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.3);
            position: relative;
            margin: 50px auto;
        }
        .booking-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; text-align: left; }
        .form-header { text-align: center; margin-bottom: 35px; }
        .form-header h2 { color: var(--primary-teal); font-size: 2.2rem; font-weight: 800; }
        
        .input-box { margin-bottom: 25px; }
        .input-box label { display: block; margin-bottom: 10px; font-weight: 700; font-size: 0.9rem; color: var(--text-dark); }
        .input-box select, .input-box input {
            width: 100%;
            padding: 16px;
            border: 2px solid #f0f0f0;
            border-radius: 18px;
            background: #fff;
            font-family: inherit;
            transition: 0.3s ease;
        }
        .input-box select:focus, .input-box input:focus {
            border-color: var(--primary-teal);
            box-shadow: 0 0 0 5px rgba(91, 178, 167, 0.1);
            outline: none;
        }

        /* MODAL OVERLAY */
        .error-modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
            display: flex; align-items: center; justify-content: center; z-index: 9999;
        }
        .error-card {
            background: white; padding: 40px; border-radius: 25px; width: 400px;
            text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body class="auth-body">

<?php if(isset($_GET['error'])): ?>
    <div class="error-modal-overlay">
        <div class="error-card" style="border-top: 6px solid #ff7675;">
            <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #ff7675; margin-bottom: 15px;"></i>
            <h2 style="margin-bottom: 10px;">Schedule Conflict</h2>
            <p style="color: var(--text-muted); margin-bottom: 25px; font-weight: 500;">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </p>
            <a href="book_appointment.php?vet_id=<?php echo $selectedVet; ?>" class="auth-btn" style="background: #ff7675; text-decoration: none; display: block;">
                Try Different Time
            </a>
        </div>
    </div>
<?php endif; ?>

<?php if(isset($_GET['success'])): ?>
    <div class="error-modal-overlay">
        <div class="error-card" style="border-top: 6px solid #00b894;">
            <i class="fas fa-check-circle" style="font-size: 3rem; color: #00b894; margin-bottom: 15px;"></i>
            <h2 style="margin-bottom: 10px;">Booking Successful!</h2>
            <p style="color: var(--text-muted); margin-bottom: 25px; font-weight: 500;">
                Your appointment has been successfully scheduled. 
                We look forward to seeing you and your pet!
            </p>
            <a href="customer_site.php" class="auth-btn" style="background: #00b894; text-decoration: none; display: block;">
                Go to Dashboard
            </a>
        </div>
    </div>
<?php endif; ?>

<div class="booking-card">
    <div class="form-header">
        <h2><i class="fas fa-calendar-check"></i> New Appointment</h2>
        <p>Choose your doctor and preferred time slot below.</p>
    </div>

    <form action="process_booking.php" method="POST">
        <div class="booking-grid">
            <div class="form-left">
                <div class="input-box">
                    <label><i class="fas fa-paw" style="color:var(--primary-teal); margin-right: 5px;"></i> Your Pet</label>
                    <select name="pet_id" required>
                        <?php
                        // Fetching pets from the database
                        $pets_stmt = $pdo->prepare("SELECT Pet_ID, Pet_Name, Pet_Type FROM Pet WHERE Owner_ID = ?");
                        $pets_stmt->execute([$owner_id]);
                        $pets_count = 0;
                        while($p = $pets_stmt->fetch()) {
                            $pets_count++;
                            echo "<option value='{$p['Pet_ID']}'>{$p['Pet_Name']} ({$p['Pet_Type']})</option>";
                        }
                        
                        if($pets_count === 0) {
                            echo "<option value='' disabled selected>No pets found. Register a pet first!</option>";
                        } else {
                            echo "<option value='' disabled selected>-- Select a Pet --</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="input-box">
                    <label><i class="fas fa-user-md" style="color:var(--primary-teal); margin-right: 5px;"></i> Veterinarian</label>
                    <select name="vet_id" required>
                        <option value="" disabled <?php echo !$selectedVet ? 'selected' : ''; ?>>-- Choose a Doctor --</option>
                        <?php
                        $vets = $pdo->query("SELECT Vet_ID, Vet_Fname, Vet_Lname FROM Vet");
                        while($v = $vets->fetch()) {
                            $isSelected = ($selectedVet == $v['Vet_ID']) ? "selected" : "";
                            echo "<option value='{$v['Vet_ID']}' $isSelected>Dr. {$v['Vet_Fname']} {$v['Vet_Lname']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="input-box">
                    <label><i class="fas fa-notes-medical" style="color:var(--primary-teal); margin-right: 5px;"></i> Medical Service</label>
                    <select name="service_type" required>
                        <option value="" disabled selected>-- Select Service --</option>
                        <option value="Vaccine">Vaccination (₱500.00)</option>
                        <option value="Checkup">General Checkup (₱300.00)</option>
                        <option value="Surgery">Major Surgery (₱5000.00)</option>
                        <option value="Grooming">Professional Grooming (₱450.00)</option>
                    </select>
                </div>
            </div>

            <div class="form-right">
                <div class="input-box">
                    <label><i class="far fa-calendar-alt" style="color:var(--primary-teal); margin-right: 5px;"></i> Preferred Date</label>
                    <input type="date" name="apt_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="input-box">
                    <label><i class="far fa-clock" style="color:var(--primary-teal); margin-right: 5px;"></i> Preferred Time</label>
                    <input type="time" name="apt_time" required>
                </div>

                <?php if($pets_count === 0): ?>
                    <div style="background: #fff9db; padding: 15px; border-radius: 12px; font-size: 0.85rem; color: #f08c00; border: 1px solid #ffe066;">
                        <i class="fas fa-info-circle"></i> It looks like you haven't registered any pets yet. Go back to your dashboard to add a pet before booking!
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div style="margin-top: 25px;">
            <button type="submit" class="auth-btn" style="padding: 20px; font-size: 1.1rem; border-radius: 20px;" <?php echo ($pets_count === 0) ? 'disabled style="background: #adb5bd; cursor: not-allowed;"' : ''; ?>>
                Confirm My Visit <i class="fas fa-chevron-right" style="margin-left:12px;"></i>
            </button>
            
            <a href="customer_site.php" style="display:block; text-align:center; margin-top:25px; text-decoration:none; color:var(--text-muted); font-weight:700; font-size:0.9rem;">
                <i class="fas fa-times"></i> Cancel and go back
            </a>
        </div>
    </form>
</div>

</body>
</html>