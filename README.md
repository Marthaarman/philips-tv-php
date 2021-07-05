# philips-tv-php

Note: this is a php code for controlling a philips smart tv based on the code from: https://github.com/eslavnov/pylips
Note: The code is only tested on a Philips 40PFS5501/12

## getting started
To initiate the tv class, send the protocol (http or https), the host (IPv4 address of the TV), the port (1926 for https, 1925 for http) and the API version. 
the API version can be found by going to: http(s)://192.168.xxx.xxx/system (replace xxx.xxx with the correct IPv4 values of the TV). A value called Major is the API version.

```php $tv = new philips_tv(protocol, host, port, APIversion);```

To connect to the TV for the first time, you need to pair. This takes two steps. The first is to call the pair function which connects to the TV and makes the TV show a pincode on screen.

```php $tv->pair();```


A pin can be given to the class by the following function. The pin is the number given by the TV. 

```php $tv->set_pin(xxxx);```


Once the pin is set, you can complete the pairing by using the following function. Be sure to complete this within 60 seconds after calling the pair() function. 

```php $tv->pair_confirm();```

Once pairing is done, all credentials are saved in credentials.json and paring should not have to be executed any longer. You can now execute commands by using the command function and giving it a command. Some have already been set in commands.json but some might need to be added by yourself. An example is given below for turning up the volume.

```php $tv->command('volume_up');```


## Demo
index.php contains a demo for some code and this might also help you pair for the first time. 

## Credits
The structure and code I based the php version on are designed by https://github.com/eslavnov for Python and you can view the pylips project here:
https://github.com/eslavnov/pylips. This allows you to control your philips tv using python
