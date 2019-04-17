# Digital Ocean Automatic Block Volume Snapshots

This script is a good and light solution that allows you to create Digital Ocean Block volume (cloud SSD connected to vitual server) snapshots both manually from browser or CLI, as by server crontab service or as a part of some bigger code solution.
The script allows you to keep your storage safe and save money by keeping always the same, defined by you, number or snapshots for any volume. The same script can be used for multiple volumes at the same time - you just need to change input parameters.

Parameters:

An array is required to be passed at DO_Volume_Backup class instance declaration with folloring parameters:

* secret (string) - required - Digital Ocean user API secret key;
* vol_name (string) - required - Digital Ocean volume name;
* vol_region (string) - requires - Volume region (e.g. nyc1, ams2 etc);
* total_snapshots (int) - optional - total number of volume snapshots tha you need to save, default 5;
* snapshot_name (string) - optional - name of the snapshot that is being created. Allows variables that will be replaced with proper values: %VOL_NAME% - volume name, %DATE_TIME% - current date and time in 12-hours format.
