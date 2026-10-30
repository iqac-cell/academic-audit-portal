<?php

namespace Mmmut\AcademicAudit\Http\Controllers;

use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function switch(Request $request)
    {
        $key = (string) $request->validate(['role' => 'required|string|max:40'])['role'];
        abort_unless($this->access()->switchTo($key), 403, 'You do not hold this role.');
        return redirect()->route('audit.dashboard')->with('status', 'You are now working as '.$this->access()->active()['label'].'.');
    }
}
