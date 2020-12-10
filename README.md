## A React NPM JEST Unit test engine for [Arcanist](https://github.com/phacility/arcanist) and [Phabricator](https://secure.phabricator.com)

A unit test engine for npm jest via react-scripts and providing results to phabricator via arcanist

## Background
This engine supports tests run by npm jest through react-scripts.


## Prerequisites
This requires `npm` and `react-scripts` and `arcanist` to be installed locally

## Installing

1. Clone this repo somewhere in your path
2. In the repo you want to run unit tests from, edit the `.arcconfig` file with settings like the following

```
{
    ...
    "unit.engine": "NpmTestJestUnitTestEngine",
    "load": [ 
        ....
        "npmtestjestunittestengine/src"
    ]
}
```

3.  In your `package.json` be sure your scripts test is set like this

```
"test": "CI=true react-scripts test",
```


A note on these settings
- `"unit.engine": "NpmTestJestUnitTestEngine"` this is the name of the engine
- `"load": [ 
        ....
        "npmtestjestunittestengine/src"
    ]` - If this does not work, specify the full path e.g. `/usr/local/src/npmtestjestunittestengine/src`


## License
All source code is licensed under the [Apache 2.0 license](LICENSE), the same license as for the Arcanist project.

## Lucit
Lucit is the company behind Layout : The application that connects big-ticket inventory applications (Automotive, Ag, Rec, Real Estate) to digital billboards, in real-time.

We stream inventory - direct, in real-time to digital billboards, anywhere. https://lucit.cc
