<?php
require __DIR__ . '/includes/bootstrap.php';
logout_user();
session_start();
flash('success', 'Sessão encerrada.');
redirect('login.php');
