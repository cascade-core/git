{
    "_": "<?php printf('_%c%c}%c',34,10,10);__halt_compiler();?>",
    "output": {
        "done": [
            "version:done"
        ]
    },
    "blocks": {
        "version_hd": {
            "block": "core/out/header",
            "in_val": {
                "text": "Version"
            },
            "in_con": {
                "enable": [
                    "version",
                    "done"
                ]
            }
        },
        "version": {
            "block": "git/version",
            "in_val": {
                "format": "details"
            }
        }
    }
}