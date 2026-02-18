<?php
function getMailer($keepAlive = false) {
    return \MailQ\Mailer::getInstance($keepAlive);
}

function getMailQ() {
    return \MailQ\Queue::getInstance();
}

