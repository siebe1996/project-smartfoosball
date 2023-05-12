<?php

namespace App\Http\Controllers;

use App\Http\Resources\GameCollection;
use App\Http\Resources\GameResource;
use App\Http\Resources\UserCollection;
use App\Models\Fooseballtable;
use App\Models\Game;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class GameApiController extends Controller
{
    /*
    public function index()
    {
        $userId = Auth::id();
        $notJoinable = new GameCollection(Game::where('start_date', '>', Carbon::now())
            ->whereHas('users', function ($q) use ($userId){
                $q->where('users.id', '=', $userId);
            })->get());

        $notJoinableIds = $notJoinable->pluck('id');
        $joinable = new GameCollection(Game::where('start_date', '>', Carbon::now())->whereNotIn('id', $notJoinableIds)->get());

        $startedGames = new GameCollection(Game::where('start_date', '<', Carbon::now())->where('end_date', '>', Carbon::now())->get());
        $previousGames = new GameCollection(Game::where('end_date', '<', Carbon::now())->get());

        return response(['data' => ['active' => ['active_joinable' => $joinable, 'active_not_joinable' => $notJoinable, 'started_games' => $startedGames], 'previous_games' => $previousGames]], 200)
            ->header('Content-Type', 'application/json');
    }*/
    /**
     * Get a list of games.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *     path="api/games",
     *     summary="Get a list of games",
     *     tags={"Games"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="inventore"),
     *                     @OA\Property(property="active", type="boolean", example=true),
     *                     @OA\Property(property="start_date", type="string", format="date-time", example="2023-05-09T05:06:41.000000Z"),
     *                     @OA\Property(property="end_date", type="string", format="date-time", example="2023-05-10T03:28:53.000000Z"),
     *                     @OA\Property(property="competition_id", type="integer", example=4),
     *                     @OA\Property(property="winner_id", type="integer", example=10),
     *                     @OA\Property(property="fooseballtable_id", type="integer", example=2),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2023-05-12T17:45:16.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-05-12T17:45:16.000000Z"),
     *                 ),
     *             ),
     *         ),
     *     ),
     * )
     */
    public function index(){
        $games = new GameCollection(Game::get());
        return response()->json(['data' => $games]);
    }
    /**
     * Store a new game.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Post(
     *     path="api/games",
     *     summary="Store a new game",
     *     tags={"Games"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"name", "unique_code", "team1_id", "team2_id"},
     *                 @OA\Property(property="name", type="string", example="Game 1"),
     *                 @OA\Property(property="unique_code", type="string", example="ABCD"),
     *                 @OA\Property(property="team1_id", type="integer", example=1),
     *                 @OA\Property(property="team2_id", type="integer", example=2),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Game made successfully"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Table doesn't exist"),
     *         ),
     *     ),
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'unique_code' => 'required|string|size:4|exists:fooseballtables',
            'team1_id' => 'exists:teams,id|integer',
            'team2_id' => 'exists:teams,id|integer',
        ]);

        try{
            $table = Fooseballtable::where('unique_code', $request->unique_code)->firstOrFail();
            $game = new Game();
            $game->name = $request->name;
            $game->active = false;
            $game->fooseballtable()->associate($table);
            $game->save();
            $game->teams()->attach([$request->team1_id, $request->team2_id]);

            return response()->json(['message' => 'Game made successfully']);
        }catch(ModelNotFoundException){
            return response()->json(['message' => 'Table doesnt exist'], 422);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if(!is_numeric($id)){
            return response(['data' => 'bad request'], 400)
                ->header('Content-Type', 'application/json');
        }
        $game = Game::with('users')->findOrFail($id)->with('usersWithPivot')->findOrFail($id);
        $alivePlayers = $game->usersWithPivot->where('pivot.alive', 1);
        $winner = $alivePlayers->count() == 1 ? $alivePlayers->first()->first_name : null;
        $mostKilled = $game->usersWithPivot->sortByDesc('pivot.kills')->take(5);
        $mostKilled = new UserCollection($mostKilled);
        $alivePlayers = new UserCollection($alivePlayers);


        return response(['data' =>['game_data' => $game, 'alive_player' => $alivePlayers, 'winner' => $winner, 'most_killed' => $mostKilled]], 200)
            ->header('Content-Type', 'application/json');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $gameId)
    {
        /*
        $request->validate([
            'gameId' => 'required'
        ]);
        $userId = Auth::id();
        $gameUser = Game::where('id', $gameId)->users()->attach($userId)->with('usersWithPivot')->get();
        return response(['data' => $gameUser], 200)
            ->header('Content-Type', 'application/json');
        */
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    /**
     * Display all current games of user.
     *
     * @return \Illuminate\Http\Response
     */
    public function current(){
        $userId = Auth::id();

        $activeGamesWithUser = new GameCollection(Game::where('active', true)
            ->whereHas('users', function ($q) use ($userId){
                $q->where('users.id', '=', $userId);
            })->get());

        return response(['data' => ['current_games' => $activeGamesWithUser]], 200)
            ->header('Content-Type', 'application/json');
    }
}
