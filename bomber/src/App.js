import React from 'react';
import './App.css';

const rows = 6;
const cols = 6;
const bombs = Math.floor( rows * cols * 0.16 );
console.log( bombs );

function Square( props ) {
    return (
        <button className={ props.className } onClick={ props.onClick }>
        { props.value }
        </button>
    );
}

class Board extends React.Component {

    renderSquare( i, j, current ) {
        return (
        <Square
            value={ this.props.squares[ i ][ j ] }
            onClick={ () => this.props.onClick( i, j ) }
            key={ current }
            className={ this.props.squares[ i ][ j ] === '*' ? 'square bomb' : 'square' }
        />
        );
    }

    generateField() {
        let col_array = [], row_array = [];
        for ( let i = 0; i < rows; i++ ) { 
            col_array = [];
            for ( let j = 0; j < cols; j++ ) {
                let current = j + i * rows;
                col_array[ current ] = this.renderSquare( i, j, current );
            }
            row_array[ i ] = <div key={ i } className="board-row">{ col_array }</div>
        }
        return row_array;
    }

    render() {
        return (
            <div>
                { this.generateField() }                        
            </div>
        );
    }
}


class Game extends React.Component {
  constructor( props ) {
    super( props );
    let fieldArray = [];
    for ( let i = 0; i < rows; i++ ) {
      fieldArray[ i ] = Array( cols ).fill( null );     
    }
    this.state = {
        history: [
            {
            squares: fieldArray
            }
        ],
        bombsArray: createArray(),
        stepNumber: 0
    };
  }

  handleClick( i, j ) {
    const squares = this.state.history[0].squares;
    if ( false === calculateWinner( this.state.history[ 0 ].squares, -1 ) || squares[ i ][ j ] ) {
        return;
    }
    squares[ i ][ j ] = calculateWinner( this.state.bombsArray, i, j );
    this.setState({
        history: [
            {
            squares: squares
            }
        ]
    });
  }

  render() {
    const winner = calculateWinner( this.state.history[ 0 ].squares, -1 );   

    let status;
    if ( false === winner ) {
        status = 'Defeat';
    } else if ( true === winner ) {
        status = 'Winner';
    } else {
        status = 'You turn';
    }

    return (
        <div className="game">
            <div className="game-board">
                <Board
                    squares={ this.state.history[ 0 ].squares }
                    onClick={ ( i, j ) => this.handleClick( i, j ) }
                />
            </div>
            <div className="game-info">
                <div>{ status }</div>                       
            </div>
        </div>
    );
  }
}

function calculateWinner( bombsArray, i, j = 0 ) {
    if ( -1 === i ) {
    for ( let k = 0; k < rows; k++ ) {
        if ( -1 !== bombsArray[ k ].indexOf( '*' ) ) {
            return false;
        }
    }
    let count = 0;
    for ( let k = 0; k < rows; k++ ) {
        for ( let l = 0; l < cols; l++ ) {
            if ( bombsArray[ k ][ l ] === null ) {
                count++;
            }
        }
    }
    if ( count === bombs ) {
        return true;
    }
    return -1;
    } else {
        return bombsArray[ i ][ j ];
    }
}

function checkCell( index, lines ) {
    if ( index > 0 && lines[ index - 1 ] !== '*' ) {
        lines[ index - 1 ] += 1;
    }
    if ( lines[ index ] !== '*' ) {
        lines[ index ] += 1;
    }
    if ( 'undefined' !== typeof( lines[ index + 1 ] ) && lines[ index + 1 ] !== '*' ) {
        lines[ index + 1 ] += 1;
    }
    return lines;
}

function createArray() {
    const lines = [];
    for ( let i = 0; i < rows; i++ ) {
        lines[ i ] = Array( cols ).fill( 0 );
    }
    let random_rows = Math.floor( Math.random() * rows );
    let random_cols = Math.floor( Math.random() * cols );
    for ( let i = 0; i < bombs; i++ ) {
        while ( lines[ random_rows ][ random_cols ] === '*' ) {
            random_rows = Math.floor( Math.random() * rows );
            random_cols = Math.floor( Math.random() * cols );
        }
        lines[ random_rows ][ random_cols ] = '*';                           
    }
    console.log( lines );
    for ( let j = 0; j < rows; j++ ) {
        for ( let k = 0; k < cols; k++ ) {
            if ( lines[ j ][ k ] === '*' ) {
                if ( j > 0 ) {
                    lines[ j - 1 ] = checkCell( k, lines[ j - 1 ] );
                }                
                lines[ j ] = checkCell( k, lines[ j ] );
                if ( 'undefined' !== typeof( lines[ j + 1 ] ) ) {
                    lines[ j + 1 ] = checkCell( k, lines[ j + 1 ] );
                }
            }
        }
    }
    return lines;
}

export default Game;