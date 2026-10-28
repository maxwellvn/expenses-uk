<?php
declare(strict_types=1);

/** Help and feedback. Open to signed-out people too, because "I cannot sign in" is the main reason to write. */
final class HelpController
{
    public function form(): void
    {
        $user = Auth::user();
        view('help', [
            'title' => 'Help and feedback',
            'user' => $user,
            'sent' => isset($_GET['sent']),
            'topic' => (string) ($_GET['topic'] ?? ($user ? 'claim' : 'access')),
        ], $user ? 'layout' : 'bare');
    }

    public function send(): void
    {
        csrf_check();
        $user = Auth::user();
        // Honeypot: real people never see or fill this field.
        if (trim((string) ($_POST['website'] ?? '')) !== '') redirect('/help?sent=1');

        $topic = (string) ($_POST['topic'] ?? '');
        $message = mb_substr(trim((string) ($_POST['message'] ?? '')), 0, 3000);
        $name = $user ? $user['name'] : mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 160);
        $handle = $user ? (string) $user['kc_handle'] : ltrim(mb_substr(trim((string) ($_POST['kc_handle'] ?? '')), 0, 80), '@');

        $errors = [];
        if (!isset(Feedback::TOPICS[$topic])) $errors['topic'] = 'Choose what this is about.';
        if (mb_strlen($message) < 10) $errors['message'] = 'Tell us a little more so we can help (at least 10 characters).';
        if (!$user && $name === '') $errors['name'] = 'Enter your name so we know who to reply to.';
        if (!$user && $handle === '') $errors['kc_handle'] = 'Enter your KingsChat handle so we can reply.';
        if (!$errors && Feedback::tooMany()) $errors['message'] = 'Too many messages from this connection in the last hour. Try again later.';
        if ($errors) {
            $_SESSION['errors'] = $errors;
            $_SESSION['old'] = ['topic' => $topic, 'message' => $message, 'name' => $name, 'kc_handle' => $handle];
            redirect('/help');
        }
        Feedback::create($user['id'] ?? null, $name, $handle, $topic, $message, mb_substr((string) ($_POST['page'] ?? ''), 0, 255));
        redirect('/help?sent=1');
    }
}
