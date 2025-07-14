<?php
// admin/settings.php
// Page to manage general application settings for administrators.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Include the admin specific header. This also handles role enforcement.
require_once '../includes/header_admin.php';

$message = ''; // Initialize message variable for feedback
$settings = []; // Array to hold fetched settings

// Fetch all current settings from the database
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log("Fetch Settings Error: " . $e->getMessage());
    $message = display_message("Could not load current settings. Please try again later.", "error");
}

// Handle form submission for updating settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Define expected settings and their default/validation rules
    $expected_settings = [
        'site_name' => [
            'type' => 'text',
            'required' => true,
            'default' => 'Assessment System'
        ],
        'site_contact_email' => [
            'type' => 'email',
            'required' => true,
            'default' => 'admin@example.com'
        ],
        'default_passing_percentage' => [
            'type' => 'number',
            'required' => true,
            'min' => 0,
            'max' => 100,
            'default' => 70
        ],
        'maintenance_mode' => [
            'type' => 'checkbox',
            'default' => 0 // 0 for off, 1 for on
        ]
    ];

    $updates_successful = true;
    $updated_count = 0;

    foreach ($expected_settings as $key => $config) {
        $value = null;

        if ($config['type'] === 'checkbox') {
            $value = isset($_POST[$key]) ? 1 : 0;
        } else {
            $value = sanitize_input($_POST[$key] ?? '');
            if ($config['required'] && empty($value) && $value !== '0') { // Allow '0' as a valid value for numbers
                $message .= display_message(ucfirst(str_replace('_', ' ', $key)) . " is required. ", "error");
                $updates_successful = false;
                continue;
            }
            if ($config['type'] === 'number') {
                if (!is_numeric($value)) {
                    $message .= display_message(ucfirst(str_replace('_', ' ', $key)) . " must be a valid number. ", "error");
                    $updates_successful = false;
                    continue;
                }
                $value = (float)$value; // Cast to float for numeric comparison
                if (isset($config['min']) && $value < $config['min']) {
                    $message .= display_message(ucfirst(str_replace('_', ' ', $key)) . " cannot be less than " . $config['min'] . ". ", "error");
                    $updates_successful = false;
                    continue;
                }
                if (isset($config['max']) && $value > $config['max']) {
                    $message .= display_message(ucfirst(str_replace('_', ' ', $key)) . " cannot be greater than " . $config['max'] . ". ", "error");
                    $updates_successful = false;
                    continue;
                }
            } elseif ($config['type'] === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $message .= display_message(ucfirst(str_replace('_', ' ', $key)) . " must be a valid email address. ", "error");
                $updates_successful = false;
                continue;
            }
        }

        // Only update if validation passes for this specific setting
        if ($updates_successful) {
            try {
                // Use INSERT ... ON DUPLICATE KEY UPDATE to either insert or update
                $stmt = $pdo->prepare("
                    INSERT INTO settings (setting_key, setting_value)
                    VALUES (:setting_key, :setting_value)
                    ON DUPLICATE KEY UPDATE setting_value = :setting_value
                ");
                $stmt->execute([
                    'setting_key' => $key,
                    'setting_value' => $value
                ]);
                $updated_count++;
                // Update the $settings array immediately for display on current page load
                $settings[$key] = $value;
            } catch (PDOException $e) {
                error_log("Update Setting Error for {$key}: " . $e->getMessage());
                $message .= display_message("Database error updating " . ucfirst(str_replace('_', ' ', $key)) . ". ", "error");
                $updates_successful = false;
            }
        }
    }

    if ($updates_successful && $updated_count > 0) {
        $message = display_message("Settings updated successfully!", "success");
    } elseif (!$updates_successful && $updated_count > 0) {
        $message = display_message("Some settings were updated, but others had errors: <br>" . $message, "warning");
    } elseif (!$updates_successful && $updated_count == 0) {
        $message = display_message("No settings were updated due to errors: <br>" . $message, "error");
    } else {
        $message = display_message("No changes were submitted.", "info");
    }
}
?>

<div class="container mx-auto p-4 py-8 max-w-full lg:max-w-4xl xl:max-w-6xl">
    <h1 class="text-3xl font-bold text-accent mb-6 text-center">System Settings</h1>

    <?php echo $message; // Display any feedback messages ?>

    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4">General Settings</h2>
        <form action="settings.php" method="POST" class="space-y-6">

            <div>
                <label for="site_name" class="block text-gray-700 text-sm font-bold mb-2">Site Name:</label>
                <input type="text" id="site_name" name="site_name"
                       value="<?php echo htmlspecialchars($settings['site_name'] ?? ''); ?>" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-accent">
                <p class="text-xs text-gray-500 mt-1">The name of your assessment platform.</p>
            </div>

            <div>
                <label for="site_contact_email" class="block text-gray-700 text-sm font-bold mb-2">Site Contact Email:</label>
                <input type="email" id="site_contact_email" name="site_contact_email"
                       value="<?php echo htmlspecialchars($settings['site_contact_email'] ?? ''); ?>" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-accent">
                <p class="text-xs text-gray-500 mt-1">Email address for general inquiries or system notifications.</p>
            </div>

            <div>
                <label for="default_passing_percentage" class="block text-gray-700 text-sm font-bold mb-2">Default Passing Percentage (%):</label>
                <input type="number" id="default_passing_percentage" name="default_passing_percentage"
                       value="<?php echo htmlspecialchars($settings['default_passing_percentage'] ?? '70'); ?>"
                       min="0" max="100" step="1" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-accent">
                <p class="text-xs text-gray-500 mt-1">The default percentage required to pass an assessment.</p>
            </div>

            <div class="flex items-center">
                <input type="checkbox" id="maintenance_mode" name="maintenance_mode" value="1"
                       <?php echo (isset($settings['maintenance_mode']) && $settings['maintenance_mode'] == 1) ? 'checked' : ''; ?>
                       class="form-checkbox h-5 w-5 text-accent rounded focus:ring-accent">
                <label for="maintenance_mode" class="ml-2 text-gray-700 font-bold">Enable Maintenance Mode</label>
                <p class="text-xs text-gray-500 ml-4">When enabled, only administrators can access the site.</p>
            </div>

            <div class="flex justify-end mt-8">
                <button type="submit"
                        class="bg-accent hover:bg-red-700 text-white font-bold py-2 px-6 rounded focus:outline-none focus:shadow-outline transition duration-300">
                    Save Settings
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Include the admin specific footer
require_once '../includes/footer_admin.php';
?>