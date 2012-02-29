; <?php exit(); __halt_compiler(); ?>

[output]
done[] = version:done

[block:version_hd]
.block		= "core/out/header"
text		= "Version"
enable[]	= "version:done"

[block:version]
.block = git/version
format = details

; vim:filetype=dosini:

