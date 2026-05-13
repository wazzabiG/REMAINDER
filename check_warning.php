<?php
// THE FAILSAFE: If this file is loaded early or opened directly, stop quietly.
if (!isset($user) || empty($user) || !isset($totalSpent)) {
    return; 
}

// Calculate hours elapsed since the cycle started
$hoursPassed = (time() - strtotime($user['cycleStartDate'])) / 3600;

// Trigger condition: Weekly cycle, within first 48 hours, spent > 50% of total allowance
if ($user['allowanceCycle'] == 'Weekly' && $hoursPassed <= 48) {
    if ($totalSpent > ($user['totalAllowance'] * 0.50)) {
        
        // Check if a warning already exists for this cycle to avoid spamming the database
        $checkWarning = $conn->prepare("SELECT WID, isAcknowledged FROM WARNING WHERE target_UID = ? AND triggeredAt >= ?");
        $checkWarning->bind_param("is", $uid, $user['cycleStartDate']);
        $checkWarning->execute();
        $warningResult = $checkWarning->get_result();

        if ($warningResult->num_rows == 0) {
            // Insert new warning (SysID 1 is a dummy system ID since the SYSTEM table just tracks state)
            $msg = "CRITICAL: You have spent over 50% of your weekly allowance in less than 48 hours!";
            $insertWarning = $conn->prepare("INSERT INTO WARNING (target_UID, SysID, message, triggeredAt) VALUES (?, 1, ?, NOW())");
            $insertWarning->bind_param("is", $uid, $msg);
            $insertWarning->execute();
        } else {
            $existingWarning = $warningResult->fetch_assoc();
            if ($existingWarning['isAcknowledged'] == 0) {
                // Display the visual warning banner if they haven't dismissed it
                echo "<div style='background-color: red; color: white; padding: 15px; font-weight: bold; text-align: center; margin-bottom: 15px;'>";
                echo "SYSTEM WARNING: You have spent over 50% of your weekly budget in the first 48 hours! ";
                echo "<a href='acknowledge_warning.php?id=" . $existingWarning['WID'] . "' style='color: yellow;'>[Dismiss]</a>";
                echo "</div>";
            }
        }
    }
}
?>