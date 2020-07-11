An xunit and clover -based unit tests runner for Arcanist.

## Installation:

Go to the directory that contains `arcanist` and `libphutil`:

```
$ ls
arcanist
liphutil
```

Clone the repository:

`git clone https://github.com/sealcode/arc-unit-mocha.git`

In the `.arcconfig` file of your projects' repository add:

```
"load": ["arc-unit-mocha/src"]
```

## Usage:

create a `test-reports` npm script that produces an `.xunit` file and a `./coverage/clover.xml` file.

in your project's .arcconfig file add:

```
	"unit.engine": "MochaEngine",
	"unit.mocha.include": ["setup-test.js", "./lib/**/*.test.js"],
```

If you're running the tests inside a docker container and the files have different paths there, enter the root project directory as it is seen inside the container into the .arcconfig file:

```
	"unit.mocha.dockerRoot": "/opt/sealious" 
```
