<?php
session_start();

function copy_dir($dir2copy, $dir_paste) {
	// On vérifie si $dir2copy est un dossier
	if(is_dir($dir2copy)):
 
		// Si oui, on l'ouvre
		if($dh = opendir($dir2copy)): 
			// On liste les dossiers et fichiers de $dir2copy
			while(($file = readdir($dh)) !== false):
				// Si le dossier dans lequel on veut coller n'existe pas, on le créé
				if(!is_dir($dir_paste)):
					mkdir ($dir_paste, 0777);
				endif;
 
				// S'il s'agit d'un dossier, on relance la fonction rÃ©cursive
				if(is_dir($dir2copy.$file) && $file != '..'  && $file != '.'):
					copy_dir ( $dir2copy.$file.'/' , $dir_paste.$file.'/' );
				// S'il sagit d'un fichier, on le copue simplement
				elseif($file != '..'  && $file != '.'):
					copy($dir2copy.$file , $dir_paste.$file);
				endif;
			endwhile;
	 
			// On ferme $dir2copy
			closedir($dh);
		endif;
	endif;
}

function delete_dir($dir) { 
	$files = array_diff(scandir($dir), array('.','..')); 
	foreach ($files as $file):
		if(is_dir($dir.'/'.$file)):
			delete_dir("$dir/$file");
		else:
			unlink($dir.'/'.$file); 
		endif;
    endforeach;
	return rmdir($dir); 
} 

if(isset($_GET['step'])):
	$response = false;
	
	// Vérification du token
	if(!isset($_GET['token']) || $_GET['token'] != $_SESSION['token']):
		$response = array(
			'status' => 'danger',
			'message' => "Une erreur s'est produit. Merci de recommencer l'installation."
		);
		goto answer;
	endif;
	
	switch($_GET['step']):
		// Vérification de l'autorisation d'écriture
		case "writable":
			if(is_writable(__DIR__)):
				$response = array(
					'status' => 'success',
					'message' => "Le dossier est disponible en écriture."
				);
			else:
				$response = array(
					'status' => 'error',
					'message' => "Une erreur s'est produit. Le dossier d'installation n'est pas inscriptible."
				);
			endif;
			break;
		
		// Téléchargement de l'application
		case "download":
			@delete_dir('app');
			@delete_dir('cache');
			@delete_dir('lang');
			@delete_dir('lib');
			@delete_dir('log');
			@delete_dir('webroot');
			
			@unlink('.gitignore');
			@unlink('.htaccess');
			@unlink('.htaccess.back');
			@unlink('config.php');
			@unlink('index.php');
			@unlink('README.md');
			
			$zip = file_get_contents('https://github.com/Tilotiti/SnakePHP/archive/master.zip');
			file_put_contents("snakephp.zip", $zip);
			
			if(!file_exists("snakephp.zip")):
				$response = array(
					'status' => 'danger',
					'message' => "Une erreur s'est produit. Les fichiers n'ont pu être téléchargés."
				);
				goto answer; 
			endif;
						
			$zip = new ZipArchive;
			if(!$zip->open('snakephp.zip')):
				$response = array(
					'status' => 'danger',
					'message' => "Une erreur s'est produit. Les fichiers n'ont pu être désarchivés."
				);
				goto answer;
			endif;
			
			$zip->extractTo(__DIR__);
			$zip->close();
			
			if(!file_exists('SnakePHP-master')):
				$response = array(
					'status' => 'danger',
					'message' => "Une erreur s'est produit. Les fichiers n'ont pu être désarchivés."
				);
				goto answer;
			endif;
			
			$response = array(
				'status' => 'success',
				'message' => "SnakePHP a été téléchargé et est prêt à être installé."
			);
			break;
		case 'generate':
			unlink('snakephp.zip');
						
			copy_dir('SnakePHP-master/', __DIR__.'/');
			
			@delete_dir('SnakePHP-master');
			@delete_dir('.git');
			
			rename('.htaccess', '.htaccess.back');
			
			mkdir('cache');
			mkdir('log');
			mkdir('lang');
			
			// Génération du fichier de configuration
			$config  = '<?php'.PHP_EOL;
			$config .= '/* Automatic installation */'.PHP_EOL;
			$config .= '/* Custom define */'.PHP_EOL;
			
			foreach($_POST as $key => $value):
				if(!empty($value)):
					$config .= "define('".$key."', '".$value."');".PHP_EOL;
				else:
					$config .= "define('".$key."', false);".PHP_EOL;
				endif;
			endforeach;
			
			$config .= '/* Basic define */'.PHP_EOL;
			$config .= "define('CHARSET', 'utf-8');".PHP_EOL;
			$config .= "define('DEV', true);".PHP_EOL;
			$config .= "define('SQLCACHETIME', 1200);".PHP_EOL;
			$config .= "define('QUERYTIMER', false);".PHP_EOL;
			$config .= ''.PHP_EOL;
			$config .= '/* Directories */'.PHP_EOL;
			$config .= "define('APP', ROOT.'/app');".PHP_EOL;
			$config .= "define('WEBROOT', ROOT.'/webroot');".PHP_EOL;
			$config .= "define('LIB', ROOT.'/lib');".PHP_EOL;
			$config .= "define('LANG', ROOT.'/lang');".PHP_EOL;
			$config .= "define('LOG', ROOT.'/log');".PHP_EOL;
			$config .= "define('CACHE', ROOT.'/cache');".PHP_EOL;
			$config .= "define('PLUGIN', LIB.'/plugin');".PHP_EOL;
			$config .= "define('SYSTEM', LIB.'/system');".PHP_EOL;
			$config .= "define('SMARTY_DIR', SYSTEM.'/class/smarty/');".PHP_EOL;
			$config .= "define('FILE', WEBROOT.'/file');".PHP_EOL;
			$config .= "define('TEMPLATE', APP.'/template');".PHP_EOL;
			$config .= "define('SOURCE', APP.'/source');".PHP_EOL;
			$config .= "define('AJAX', APP.'/ajax');".PHP_EOL;
			
			file_put_contents('config.php', $config);
			@rename('.htaccess.back', '.htaccess');
			@unlink('installer.php');
			
			@chmod('app', 0755);
			@chmod('cache', 0777);
			@chmod('lang', 0777);
			@chmod('lib', 0755);
			@chmod('log', 0777);
			
			$response = array(
				'status' => 'success',
				'message' => "SnakePHP a été configuré. Vous allez être redirigé."
			);
			break;
	endswitch;
	
	answer:
	header('Content-Type: application/json');
	exit(json_encode($response));
else:
	header('Content-Type: text/html; charset=utf-8');
	session_destroy();
	session_start();
	$_SESSION['token'] = rand(0, 100000000);
endif;
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8"/>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="[meta:description]">
    <meta name="keywords" content="[meta:keywords]">
    
	<title>SnakePHP - Installation</title>
	
	<link rel="stylesheet" media="screen" type="text/css" href="//rawgithub.com/Tilotiti/SnakePHP/master/webroot/css/snakephp/bootstrap.css" />
	<link rel="stylesheet" media="screen" type="text/css" href="//rawgithub.com/Tilotiti/SnakePHP/master/webroot/css/snakephp/main.css" />
	
	<script type="text/javascript" src="//rawgithub.com/Tilotiti/SnakePHP/master/webroot/js/snakephp/jquery.js"></script>
	<script type="text/javascript" src="//rawgithub.com/Tilotiti/SnakePHP/master/webroot/js/snakephp/bootstrap.js"></script>
</head>
<body>
	<div id="main">
		<h1>SnakePHP - Installation</h1>
		<div id="content">
			<p>Vous êtes sur le point de démarrer le processus d'installation automatique. Souhaitez-vous continuer ?</p>
			<p class="text-center">
				<button class="btn btn-success" id="start">
					<i class="glyphicon glyphicon-ok"></i> Commencer
				</button>
			</p>
		</div>
	</div>
	
	<script>
		$(function() {
			var content = $('#content');
			
			$('#start').on('click', function() {
				content.trigger('writable');
			});
			
			// Etape 1 : Vérification des droits en écriture sur le dossier
			content.on('writable', function() {
				post('writable', undefined, function(data) {
					button("Télécharger", "download", content);
				});
			});
			
			// Etape 2 : Téléchargement
			content.on('download', function() {
				content.html('<p>Le téléchargement de SnakePHP est en cours, cela peut prendre plusieurs minutes.</p>');
				post('download', undefined, function(data) {
					button("Configurer", "configure", content);
				});
			});
			
			// Etape 3 : Configuration
			content.on('configure', function() {
				// Création du formulaire de configuration
				content.html('<p>Configuration de SnakePHP :</p>');
				
				var form = $('<form />');
					form.appendTo(content);
					
				form.append("<h2>Paramètres</h2>");
					
				form.append(input({
					name:    "ROOT",
					label:   "Dossier d'installation",
					value:   server.root,
					require: true
				}));
				
				form.append(input({
					name:    "SITE",
					label:   "Nom du site",
					require: true
				}));
				
				form.append(input({
					name:    "URL",
					label:   "Adresse URL du site",
					require: true,
					value:   server.url
				}));
				
				form.append(input({
					name:    "IPADMIN",
					label:   "Adresse IP du développeur (accès à la barre de développement)",
					require: false,
					value: server.ip
				}));
				
				form.append(input({
					name:    "TIMEZONE",
					label:   "Fuseau horaire",
					require: false,
					value:   "Europe/Paris"
				}));
				
				form.append("<h2>Base de donnée</h2>");
				form.append("<p>Laissez vide si vous ne souhaitez pas vous connecter à une base de donnée</p>");
				
				form.append(input({
					name:    "DBHOST",
					label:   "Hôte de la base de donnée",
					require: false,
					value:  false
				}));
				
				form.append(input({
					name:    "DBUSER",
					label:   "Nom d'utilisateur",
					require: false,
					value:   false
				}));
				
				form.append(input({
					name:    "DBPASS",
					label:   "Mot de passe",
					require: false,
					value:   false
				}));
				
				form.append(input({
					name:    "DBNAME",
					label:   "Nom de la base de donnée",
					require: false,
					value:   false
				}));
				
				form.append(input({
					name:    "DBPREF",
					label:   "Préfix des noms de table",
					require: false,
					value:   "snake_"
				}));
				
				button("Générer le fichier de configuration", "generate", content);
			});
			
			content.on('generate', function() {
				if($('form').length == 0) {
					location.reload();
				} else {
					post('generate', $('form').serialize(), function(data) {
						window.setTimeout(function() {
							document.location.href = '/';
						}, 3000);
					});
				}
			});
		});
		
		var server = {
			root: '<?php echo $_SERVER['DOCUMENT_ROOT'] ?>',
			url: 'http://<?php echo $_SERVER['HTTP_HOST'] ?>',
			ip: '<?php echo $_SERVER['REMOTE_ADDR'] ?>'
		}
		
		var post = function(step, params, success) {
			
			if(params == undefined) {
				params = {};
			}
							
			var token = "<?php echo $_SESSION['token']; ?>";
			
			$.post('/installer.php?step='+step+'&token='+token, params, function(data) {
				var content = $('#content');
					content.empty();
				
				var message = $('<div />');
					message.addClass('alert alert-'+data.status);
					message.html(data.message);
					message.appendTo(content);
					
				if(data.status == "danger") {
					var p = $('<p />');
						p.addClass('text-center');
						p.appendTo(content);
						
					var restart = $('<button />');
						restart.addClass('btn btn-danger');
						restart.html('<i class="glyphicon glyphicon-ok"></i> Recommener');
						restart.appendTo(p);
						restart.on('click', function() {
							content.trigger(step);
						});
						
					return false;
				} else if(data.status == "success") {
					success(data);
				}
			}, 'json');
		}
		
		var button = function(text, step, content) {
			var p = $('<p />');
				p.addClass('text-center');
				p.appendTo(content);
				
			var next = $('<button />');
				next.addClass('btn btn-success');
				next.html('<i class="glyphicon glyphicon-ok"></i> '+text);
				next.appendTo(p);
				next.on('click', function() {
					content.trigger(step);
				});
		}
		
		var input = function(params) {
			var div = $('<div />');
				div.addClass('form-group');
				
			var label = $('<label />');
				label.attr('for', params.name);
				label.html(params.label);
				label.appendTo(div);
				
			var input = $('<input />');
				input.addClass('form-control');
				input.attr('id', params.name || false);
				input.attr('name', params.name || false);
				input.attr('placeholder', params.name || false);
				input.attr('required', params.require || false);
				if(params.value) input.val(params.value);
				input.appendTo(div);
				
			return div;
		}
	</script>
</body>
</html>