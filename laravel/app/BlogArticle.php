<?php

namespace AGarage;

use Illuminate\Database\Eloquent\Model;

class BlogArticle extends Model
{
    public function topics() {
        return $this->belongsToMany('AGarage\BlogTopic');
    }

    public function comments() {
        return $this->hasMany('AGarage\BlogComment');
    }
}
