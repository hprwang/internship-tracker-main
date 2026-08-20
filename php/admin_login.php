<?php
/**
 * The admin and student login flows have been unified into a single
 * login page. This file is kept only so old bookmarks/links don't 404;
 * it simply forwards to the unified login page, which routes admins
 * and students to the correct dashboard automatically after sign in.
 */
header('Location: ../index.php');
exit;
