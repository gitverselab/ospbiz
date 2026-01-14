<?php
require_once ROOT_PATH . '/app/models/JournalModel.php';

class JournalController {

    // Dashboard
    public function index() {
        $childView = ROOT_PATH . '/app/views/dashboard.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // Show Create Form
    public function create() {
        // Fetch accounts for the dropdown
        $db = Database::getInstance();
        $accounts = $db->query("SELECT * FROM accounts")->fetchAll();

        $childView = ROOT_PATH . '/app/views/journal/create.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // Show List of Journals
    public function list() {
        $db = Database::getInstance();
        // Fetch journals with user name
        $sql = "SELECT j.*, u.username 
                FROM journal_entries j 
                LEFT JOIN users u ON j.created_by = u.id 
                ORDER BY j.date DESC, j.id DESC";
        $journals = $db->query($sql)->fetchAll();

        $childView = ROOT_PATH . '/app/views/journal/list.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }
}