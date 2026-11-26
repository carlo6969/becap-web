<?php
if (csrf_ok()) { $_SESSION = []; session_regenerate_id(true); session_destroy(); }
redirect('entrar');
