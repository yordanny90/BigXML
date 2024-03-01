[repo]:  https://github.com/yordanny90/BigXML
[iconGit]: http://www.google.com/s2/favicons?domain=www.github.com

# BigXML

Esta librería se utiliza para leer archivos XML demasiado grandes para cargar todos los datos en memoria.

[Ir a ![GitHub CI][iconGit]][repo]

# Requisitos mínimos

PHP 7.1+, PHP 8.0+

Clase `XMLReader` de [libxml](https://www.php.net/manual/en/book.libxml.php)

# Ejemplo

La clase principal es `\BigXML\File` como se muestra en el ejemplo:
```PHP
$file='ruta del archivo .xml';
$xml=new \BigXML\File($file);
$reader=$xml->getReader('main/ruta/del/nodo');
if($reader){
    foreach($reader AS $index=>$valor){
        // Aquí el uso para cada nodo en la ruta encontrada
    }
}
```
```PHP
$file='ruta del archivo .xml';
$xml=new \BigXML\File($file);
// Recorrido rápido del XML para comprobar que no existan errores de sintaxis
$valid=$xml->validXML();
// Mapa (array) de la estructura completa del XML con un conteo de nodos
$map=$xml->makeMap();
// Lista de todas las rutas posibles
$mapList=$xml->makeMapList();
```
