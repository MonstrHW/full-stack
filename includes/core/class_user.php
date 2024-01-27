<?php

class User
{

    // GENERAL

    public static function user_info($d)
    {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : 0;
        // where
        if ($user_id) $where = "user_id='" . $user_id . "'";
        else if ($phone) $where = "phone='" . $phone . "'";
        else $where = 0;
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, last_name, phone, email, access FROM users WHERE " . $where . " LIMIT 1;") or die(DB::error());
        if ($row = DB::fetch_row($q)) {
            return [
                'id' => (int) $row['user_id'],
                'plot_id' => $row['plot_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone_str' => phone_formatting($row['phone']),
                'email' => $row['email'],
                'access' => (int) $row['access']
            ];
        } else {
            return [
                'id' => 0,
                'plot_id' => '',
                'first_name' => '',
                'last_name' => '',
                'phone_str' => '',
                'email' => '',
                'access' => 0
            ];
        }
    }

    public static function users_list($d = [])
    {
        // vars
        $search = isset($d['search']) && trim($d['search']) ? $d['search'] : '';
        $offset = isset($d['offset']) && is_numeric($d['offset']) ? $d['offset'] : 0;
        $limit = 20;
        $items = [];
        // where
        $where = [];
        if ($search) {
            $where[] = "phone LIKE '%" . $search . "%'";
            $where[] = "first_name LIKE '%" . $search . "%'";
            $where[] = "email LIKE '%" . $search . "%'";
        }

        $where = $where ? "WHERE " . implode(" OR ", $where) : "";
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, last_name, phone, email, last_login FROM users " . $where . " LIMIT " . $offset . ", " . $limit . ";") or die(DB::error());
        while ($row = DB::fetch_row($q)) {
            $items[] = [
                'id' => (int) $row['user_id'],
                'plot_id' => $row['plot_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone_str' => phone_formatting($row['phone']),
                'email' => $row['email'],
                'last_login' => $row['last_login'] ? date('Y/m/d', $row['last_login']) : 'N/A',
            ];
        }
        // paginator
        $q = DB::query("SELECT count(*) FROM users " . $where . ";");
        $count = ($row = DB::fetch_row($q)) ? $row['count(*)'] : 0;
        $url = 'users?';
        if ($search) $url .= 'search=' . $search . '&';
        paginator($count, $offset, $limit, $url, $paginator);
        // output
        return ['items' => $items, 'paginator' => $paginator];
    }

    public static function users_fetch($d = [])
    {
        $info = User::users_list($d);
        HTML::assign('users', $info['items']);
        return ['html' => HTML::fetch('./partials/users_table.html'), 'paginator' => $info['paginator']];
    }

    public static function user_edit_window($d = [])
    {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        HTML::assign('user', User::user_info(compact('user_id')));
        return ['html' => HTML::fetch('./partials/user_edit.html')];
    }

    public static function user_edit_update($d = [])
    {
        // vars
        $user_id  = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $first_name = isset($d['first_name']) && trim($d['first_name']) ? trim($d['first_name']) : '';
        $last_name = isset($d['last_name']) && trim($d['last_name']) ? trim($d['last_name']) : '';
        $phone = isset($d['phone']) && trim($d['phone']) ? trim($d['phone']) : '0';
        $email = isset($d['email']) && trim($d['email']) ? strtolower(trim($d['email'])) : '';
        $plot_id  = isset($d['plot_id']) && trim($d['plot_id']) ? trim($d['plot_id']) : '';
        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;

        if (!$first_name || !$last_name || !$phone === '' || !$email) {
            return [
                'status' => 'error',
                'message' => 'Please fill all fields and optionally Plots.'
            ];
        }

        $phone = preg_replace('~\D+~', '', $phone);
        if (!$phone) $phone = '0';

        // update
        if ($user_id) {
            $set = [];
            $set[] = "first_name='" . $first_name . "'";
            $set[] = "last_name='" . $last_name . "'";
            $set[] = "email='" . $email . "'";
            $set[] = "phone='" . $phone . "'";
            $set[] = "plot_id='" . $plot_id . "'";
            $set[] = "updated='" . Session::$ts . "'";
            $set = implode(", ", $set);
            DB::query("UPDATE users SET " . $set . " WHERE user_id='" . $user_id . "' LIMIT 1;") or die(DB::error());
        } else {
            DB::query("INSERT INTO users (
                first_name,
                last_name,
                email,
                phone,
                plot_id
            ) VALUES (
                '" . $first_name . "',
                '" . $last_name . "',
                '" . $email . "',
                '" . $phone . "',
                '" . $plot_id . "'
            );") or die(DB::error());
        }
        // output
        return User::users_fetch(['offset' => $offset]);
    }

    public static function user_delete($d = [])
    {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;

        if ($user_id) {
            DB::query("DELETE FROM users WHERE user_id='" . $user_id . "';") or die(DB::error());
        }

        return User::users_fetch(['offset' => $offset]);
    }

    public static function users_list_plots($number)
    {
        // vars
        $items = [];
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, email, phone
            FROM users WHERE plot_id LIKE '%" . $number . "%' ORDER BY user_id;") or die(DB::error());
        while ($row = DB::fetch_row($q)) {
            $plot_ids = explode(',', $row['plot_id']);
            $val = false;
            foreach ($plot_ids as $plot_id) if ($plot_id == $number) $val = true;
            if ($val) $items[] = [
                'id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'email' => $row['email'],
                'phone_str' => phone_formatting($row['phone'])
            ];
        }
        // output
        return $items;
    }
}
