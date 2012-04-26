<?php
/*
 * Copyright (c) 2010, Josef Kufner  <jk@frozen-doe.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. Neither the name of the author nor the names of its contributors
 *    may be used to endorse or promote products derived from this software
 *    without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE REGENTS AND CONTRIBUTORS ``AS IS'' AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED.  IN NO EVENT SHALL THE REGENTS OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
 * OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
 * SUCH DAMAGE.
 */

/**
 * Load version ifo using Glip library. This is replacement of
 * core/devel/version block. No external binary is executed.
 */

class B_git__version extends Block
{
	const force_exec = true;

	protected $inputs = array(
		'filename' => '{DIR_ROOT}var/version.ini.php', // Version info file.
		'format' => 'short',	// 'short' = only app version, 'details' = everything
		'link' => null,		// When 'short' format, link to this url.
		'prefix' => null,	// When 'short' format, prepend this string (some delimiter or so).
		'suffix' => null,	// When 'short' format, append this string (some delimiter or so).
		'slot' => 'default',
		'slot_weight' => 50,
	);

	protected $outputs = array(
		'done' => true,
	);

	public function main()
	{
		$version_file = template_format($this->in('filename'), get_defined_constants(), null);
		$version_mtime = @filemtime($version_file);
		$version_size = @filesize($version_size);

		$format = $this->in('format');

		// Check if version.ini needs update
		$need_update = false;
		if (@filemtime(__FILE__) > $version_mtime) {
			// This PHP code changed
			$need_update = true;
		} else if (!$version_mtime || ($version_size < 10 && $version_mtime + 28800 < time()) || $this->is_git_repo_newer($version_mtime, DIR_ROOT)) {
			// Short format needs only app version, so do not check everything
			$need_update = true;
		} else if ($format != 'short') {
			// If format is not 'short', check core and all plugins
			if ($this->is_git_repo_newer($version_mtime, DIR_CORE)) {
				$need_update = true;
			} else {
				if ($version_mtime < filemtime(DIR_PLUGIN)) {
					// new, deleted or renamed block
					$need_update = true;
				} else {
					foreach (get_plugin_list() as $plugin) {
						if ($this->is_git_repo_newer($version_mtime, DIR_PLUGIN.$plugin.'/')) {
							$need_update = true;
							break;
						}
					}
				}
			}
		}

		// Update version.ini
		if ($need_update) {
			$this->build_version_file($version_file);
		}

		// Get version data
		$version = parse_ini_file($version_file, TRUE);

		// Show version (if present)
		if (!empty($version)) {
			$this->template_add(null, 'core/version', array(
					'version' => $version,
					'format'  => $format,
					'link'    => $this->in('link'),
					'prefix'  => $this->in('prefix'),
					'suffix'  => $this->in('suffix'),
				));
			$this->out('done', true);
		}
	}


	private function is_git_repo_newer($ref_mtime, $basedir)
	{
		$gitdir = $basedir.'.git';
		if (is_file($gitdir)) {
			$repo = file_get_contents($gitdir);
			if (preg_match('/^gitdir: (.*)/', $repo, $m)) {
				$gitdir = realpath($m[1]);
			}
		}

		$head_file = $gitdir.'/HEAD';

		if ($ref_mtime < @filemtime($head_file)) {
			return true;
		}

		$head = @ file_get_contents($head_file);

		if ($head === FALSE) {
			return false;
		}
		if (sscanf($head, 'ref: %s', $ref_file) == 1) {
			return $ref_mtime < @filemtime($basedir.'.git/'.$ref_file);
		} else {
			return false;
		}
	}


	private function build_version_file($file)
	{
		$info['app'] = $this->get_info(DIR_ROOT.'.git');
		$info['core'] = $this->get_info(DIR_CORE.'.git');

		foreach (get_plugin_list() as $plugin) {
			$dir = DIR_PLUGIN.$plugin.'/.git';
			if (file_exists($dir)) {
				$info['plugin:'.$plugin] = $this->get_info($dir);
			} else {
				$info['plugin:'.$plugin] = array(
					'note' => _('Plugin is part of the application.'),
				);
			}
		}

		foreach ($info as & $i) {
			if ($i === false) {
				$i = array(
					'error' => _('Failed to read git repository.'),
				);
			}
		}

		//NDebug::barDump($info, 'Version info');

		write_ini_file($file, $info, TRUE,
			 ";\074?php echo \"<pre style=\\\"margin:0;\\\">\\n\"; ?\076\n"
			.";\n"
			.";\tVersion info - generated by git/version block\n"
			.";\n"
			.";\074?php\n"
			.";       \$v = parse_ini_file(__FILE__, TRUE);\n"
			.";       function m(\$m, \$s) { return max(\$m, strlen(\$s)); };\n"
			.";       \$w = array_reduce(array_keys(\$v), 'm', 0) + 1;\n"
			.";       printf(\"\\n;  %-\".\$w.\"s | %-20s | %s\", 'Part', 'Version', 'Date');\n"
			.";       echo \"\\n; \", str_repeat('-', \$w + 53);\n"
			.";       foreach(\$v as \$s => \$vv) {\n"
			.";               printf(\"\\n;  %-\".\$w.\"s | %-20s | %s\", \$s, @\$vv['version'], @\$vv['date']);\n"
			.";       }\n"
			.";       echo \"\\n; \", str_repeat('-', \$w + 53), \"\\n;\\n</pre></html>\";\n"
			.";       __halt_compiler();\n"
			.";?\076\n"
			.";\n");
	}


	private function get_info($repo_dir)
	{
		if (!class_exists('Git')) {
			require(dirname(dirname(__FILE__)).'/glip/lib/glip.php');
		}

		try {
			$repo = new Git($repo_dir);
			$head = $repo->getHead();

			// Current version
			$version = $repo->describe($head);

			// Last commit's date
			$last_commit = $repo->getObject($head);
			if ($last_commit->getType() == Git::OBJ_COMMIT) {
				$date = sprintf("%s %s%02d%02d",
						gmstrftime('%F %T', $last_commit->author->time + $last_commit->author->offset),
						$last_commit->author->offset >= 0 ? '+':'-',
						(int) abs($last_commit->author->offset / 3600),
						abs($last_commit->author->offset / 60) % 60);
			}

			// Repository URL
			$config = parse_ini_file($repo->dir.'/config', TRUE);
			$url = $config['remote origin']['url'];

			return array(
				'version' => $version,
				'date' => $date,
				'origin' => $url,
			);
		}
		catch(Exception $e) {
			error_msg($e->getMessage());
			return FALSE;
		}
	}
}

