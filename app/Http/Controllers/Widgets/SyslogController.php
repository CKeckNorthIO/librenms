<?php

/**
 * SyslogController.php
 *
 * -Description-
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @link       https://www.librenms.org
 *
 * @copyright  2018 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace App\Http\Controllers\Widgets;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SyslogController extends WidgetController
{
    protected string $name = 'syslog';
    protected $defaults = [
        'title' => null,
        'device' => null,
        'device_group' => null,
        'hidenavigation' => 0,
        'level' => null,
    ];

    public function getSettingsView(Request $request): View
    {
        $data = $this->getSettings(true);

        $data['device'] = Device::hasAccess($request->user())->find($data['device']);

        $data['priorities'] = app('translator')->get('syslog.severity');

        return view('widgets.settings.syslog', $data);
    }
}
