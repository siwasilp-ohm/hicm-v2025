<?php
/**
 * Email Helper Functions
 * Handles email sending with template support
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Send email using configured SMTP or mail()
 */
function sendEmail($to, $subject, $body, $isHTML = true) {
    $db = getDB()->getConnection();
    
    // Get email settings
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%' OR setting_key = 'contact_email'");
    $stmt->execute();
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $smtpEnabled = isset($settings['smtp_enabled']) && $settings['smtp_enabled'] === 'true';
    $fromEmail = $settings['contact_email'] ?? 'noreply@hicm.gov.th';
    $fromName = $settings['smtp_from_name'] ?? 'HICM V2025';
    
    if ($smtpEnabled && !empty($settings['smtp_host'])) {
        // Use SMTP with ini_set for configuration
        ini_set('SMTP', $settings['smtp_host']);
        ini_set('smtp_port', $settings['smtp_port'] ?? '587');
        ini_set('sendmail_from', $fromEmail);
    }
    
    $headers = "MIME-Version: 1.0\r\n";
    if ($isHTML) {
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    }
    $headers .= "From: " . $fromName . " <" . $fromEmail . ">\r\n";
    $headers .= "Reply-To: " . $fromEmail . "\r\n";
    
    return mail($to, $subject, $body, $headers);
}

/**
 * Get email template by key
 */
function getEmailTemplate($templateKey) {
    $db = getDB()->getConnection();
    
    $stmt = $db->prepare("SELECT * FROM email_templates WHERE template_key = ?");
    $stmt->execute([$templateKey]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get all email templates
 */
function getAllEmailTemplates() {
    $db = getDB()->getConnection();
    
    $stmt = $db->query("SELECT * FROM email_templates ORDER BY template_key");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Update email template
 */
function updateEmailTemplate($templateKey, $subject, $body) {
    $db = getDB()->getConnection();
    
    $stmt = $db->prepare("UPDATE email_templates SET subject = ?, body = ?, updated_at = NOW() WHERE template_key = ?");
    return $stmt->execute([$subject, $body, $templateKey]);
}

/**
 * Replace variables in email template
 */
function replaceEmailVariables($text, $variables) {
    foreach ($variables as $key => $value) {
        $text = str_replace('{' . $key . '}', $value, $text);
    }
    return $text;
}

/**
 * Send templated email
 */
function sendTemplatedEmail($to, $templateKey, $variables = []) {
    $template = getEmailTemplate($templateKey);
    
    if (!$template) {
        return false;
    }
    
    $subject = replaceEmailVariables($template['subject'], $variables);
    $body = replaceEmailVariables($template['body'], $variables);
    
    return sendEmail($to, $subject, $body, true);
}
