<?php
include 'config/db.php';
?>
<div class="booking-header">
    <p style="color:var(--text-muted); font-weight:600;">Veterinarians</p>
</div>
dawdwa
<div class="vet-list">
    <?php
    $stmt = $pdo->query("SELECT * FROM Vet");
    while($vet = $stmt->fetch()) {
        echo "
        <div class='glass-card vet-card'>
            <img src='https://ui-avatars.com/api/?name=Vet+{$vet['Vet_Lname']}&background=random' class='vet-avatar-large'>
            <div class='vet-details'>
                <h3>Dr. {$vet['Vet_Fname']} {$vet['Vet_Lname']}</h3>
                <p class='specialty'>{$vet['Specialization']}</p>
                <div class='rating'><i class='fas fa-star'></i><i class='fas fa-star'></i><i class='fas fa-star'></i><i class='fas fa-star'></i><i class='fas fa-star-half-alt'></i> (4.8)</div>
                <p style='font-size:0.8rem; color:var(--text-muted); margin:10px 0;'><i class='fas fa-map-marker-alt'></i> HappyPaws Central Clinic, Manila</p>
                
                <div class='time-slots'>
                    <span>08:00 AM</span>
                    <span>10:30 AM</span>
                    <span>02:00 PM</span>
                    <span class='see-more'>See more ></span>
                </div>
            </div>
            <div class='vet-action'>
                <p style='font-size:0.8rem; text-align:right;'>Consultation Fee <br> <b style='font-size:1.2rem; color:var(--primary-teal);'>₱500</b></p>
                <button class='btn-book' onclick='alert(\"Booking Slot...\")'>BOOK APPOINTMENT</button>
            </div>
        </div>";
    }
    ?>
</div>