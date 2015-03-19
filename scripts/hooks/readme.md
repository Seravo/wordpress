# Git Hooks

All files in this folder will be symlinked into .git/hooks/
These are scripts which will be run when you run specific git command.

Here's a full list of hooks you can attach scripts to:

* applypatch-msg
* pre-applypatch
* post-applypatch
* pre-commit
* prepare-commit-msg
* commit-msg
* post-commit
* pre-rebase
* post-checkout
* post-merge
* pre-receive
* update
* post-receive
* post-update
* pre-auto-gc
* post-rewrite
* pre-push

Use precisely the same names as described here. Otherwise git won't understand you.