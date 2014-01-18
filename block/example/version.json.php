{
    "_": "<?php printf('_%c%c}%c',34,10,10);__halt_compiler();?>",
    "output": {
        "done": [
            "version:done"
        ]
    },
    "block:version_hd": {
        ".block": "core/out/header",
        "text": "Version",
        "enable": [
            "version:done"
        ]
    },
    "block:version": {
        ".block": "git/version",
        "format": "details"
    }
}