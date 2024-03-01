[repo]:  https://github.com/yordanny90/BigXML
[iconGit]: http://www.google.com/s2/favicons?domain=www.github.com

# Librería BigXLSX

Esta librería se utiliza para leer archivos de XLSX demasiado grandes para cargar todos los datos en memoria.

[Ir a ![GitHub CI][iconGit]][repo]

# Requisitos

PHP 7.1+, PHP 8.0+

Módulo [zlib](https://www.php.net/manual/es/book.zlib.php)

La librería [BigXML](../BigXML/README.md)

## Ejemplo

La clase principal es `\BigXLSX\Reader` como se muestra en el ejemplo:
```PHP
$file='ruta del archivo.xlsx';
$xlsx=new \BigXLSX\Reader($file);
// Lista de hojas con su rId => nombre
$sheets=$xlsx->getSheetrIdNames();
// Obtiene la primera hoja visible del archivo Excel
// El rId de la primera hoja no siempre es el mismo
reset($sheets);
$sheet1=$xlsx->getSheetByrId(key($sheets));
// Nombre de la primera hoja
$name=$sheet1->name;
foreach($sheet1 AS $k=>$row){
    // Aquí el uso para cada row de la hoja
}
```