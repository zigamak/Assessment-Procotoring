<?php
// includes/footer_admin.php
// Footer template for the Admin dashboard.

// Ensure BASE_URL is available for consistent links
require_once 'db.php';

// Define the theme color for consistent styling (optional in footer, but good for consistency)
$theme_color = "#1e4b31";
?>
        </main>
        <footer class="bg-gray-800 text-white p-4 text-center mt-auto">
            <div class="container mx-auto">
                <p>&copy; <?php echo date("Y"); ?> Assessment Proctoring System. Admin Panel.</p>
                <p class="text-sm mt-1">Admin contact: <a href="mailto:admin@assessmentsystem.com" class="text-blue-400 hover:underline">admin@assessmentsystem.com</a></p>
            </div>
        </footer>
    </div> <!-- Closes the flex-1 div for main content area -->
</body>
</html>
