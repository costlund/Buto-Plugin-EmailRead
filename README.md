# Buto-Plugin-EmailRead
- Read emails from server.

## Usage
```
wfPlugin::includeonce('email/read');
$email_read = new PluginEmailRead();
$email_read->server = 'mail.world.com';
$email_read->port = '143';
$email_read->user = 'me@world.com';
$email_read->password = '112233';
$email_read->folder = '';
$messages = $email_read->get_messages();
wfHelp::print($messages->get(), true);
```
